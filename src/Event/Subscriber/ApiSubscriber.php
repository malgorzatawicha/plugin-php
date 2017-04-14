<?php
namespace Athena\Event\Subscriber;

use Athena\Event\HttpTransactionCompleted;
use Athena\Event\Proxy\BehatProxy;
use Athena\Event\UnitSuiteCompleted;
use Athena\Event\UnitTestCompleted;
use Athena\Event\UnitTestIncomplete;
use Athena\Logger\Builder\BddReportBuilder;
use Athena\Logger\Builder\UnitReportBuilder;
use Athena\Logger\Interpreter\InterpreterInterface;
use Athena\Logger\Timer\TimerInterface;
use Athena\Logger\TrafficLoggerInterface;
use Athena\Stream\OutputStreamInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ApiSubscriber implements EventSubscriberInterface
{
    /**
     * @var \Athena\Logger\Interpreter\InterpreterInterface
     */
    private $interpreter;

    /**
     * @var UnitReportBuilder
     */
    private $report;

    /**
     * @var \Athena\Stream\OutputStreamInterface
     */
    private $outputStream;

    /**
     * @var TrafficLoggerInterface
     */
    private $trafficLogger;

    /**
     * @var \Athena\Logger\Timer\TimerInterface
     */
    private $timer;

    /**
     * @var HttpTransactionCompleted[]
     */
    private $afterHttpTransactionEvents = [];

    private $suiteStartedCount;

    /**
     * BddSubscriber constructor.
     *
     * @param \Athena\Logger\Interpreter\InterpreterInterface $interpreter
     * @param \Athena\Stream\OutputStreamInterface            $outputStream
     * @param \Athena\Logger\Timer\TimerInterface             $timer
     */
    public function __construct(
        InterpreterInterface $interpreter,
        OutputStreamInterface $outputStream,
        TimerInterface $timer
    ) {
        $this->interpreter = $interpreter;
        $this->outputStream = $outputStream;
        $this->report = new UnitReportBuilder();
        $this->timer = $timer;
    }

    /**
     * @inheritdoc
     */
    public static function getSubscribedEvents()
    {
        return [
            UnitSuiteCompleted::BEFORE => ['startTestSuite', -50],
            UnitTestCompleted::BEFORE => ['startTest', -50],
            UnitTestCompleted::AFTER => ['endTest', -50],
            UnitTestIncomplete::ERROR => ['addError', -50],
            UnitTestIncomplete::FAILURE => ['addFailure', -50],
            UnitTestIncomplete::SKIPPED => ['addSkipped', -50],
            UnitTestIncomplete::WARNING => ['addWarning', -50],
            UnitTestIncomplete::INCOMPLETE => ['addIncomplete', -50],
            UnitTestIncomplete::RISKY => ['addRisky', -50],
            UnitSuiteCompleted::AFTER => ['endTestSuite', -50],
            HttpTransactionCompleted::AFTER => ['addHttpTransaction', -50]
        ];
    }

    /**
     * @param \Athena\Event\UnitSuiteCompleted $event
     */
    public function startTestSuite(UnitSuiteCompleted $event)
    {
        // Dodge PHP Unit strange behaviour with multiple testing suites
        if ($event->getTestSuite()->getName() == null) {
            return;
        }

        if ($this->suiteStartedCount++ > 0) {
            $this->report->startChildTestSuite($event->getTestSuite()->getName());
            return;
        }

        $this->report->startTestSuite($event->getTestSuite()->getName());
    }

    /**
     * @param \Athena\Logger\TrafficLoggerInterface $trafficLogger
     *
     * @return $this
     */
    public function setTrafficLogger(TrafficLoggerInterface $trafficLogger)
    {
        $this->trafficLogger = $trafficLogger;
        return $this;
    }

    /**
     * @param \Athena\Event\UnitTestCompleted $event
     */
    public function startTest(UnitTestCompleted $event)
    {
        if ($this->trafficLogger instanceof TrafficLoggerInterface) {
            $this->trafficLogger->start();
        }

        $this->report->startTest($event->getTest()->getName());
    }

    /**
     * @param \Athena\Event\UnitTestIncomplete $event
     */
    public function addError(UnitTestIncomplete $event)
    {
        $this->report->addError(get_class($event->getException()), $event->getException()->getMessage(), $event->getException()->getTraceAsString());
    }

    /**
     * @param \Athena\Event\UnitTestIncomplete $event
     */
    public function addFailure(UnitTestIncomplete $event)
    {
        $msg = $event->getException()->getMessage();
        $exception = $event->getException();
        if (method_exists($exception, 'getComparisonFailure') && !empty($event->getException()->getComparisonFailure())) {
            $msg = $event->getException()->getComparisonFailure()->toString();
        }

        $this->report->addFailure(get_class($event->getException()), $msg, $event->getException()->getTraceAsString());
    }

    /**
     * @param \Athena\Event\UnitTestIncomplete $event
     */
    public function addRisky(UnitTestIncomplete $event)
    {
        $this->report->addRisky(get_class($event->getException()), $event->getException()->getMessage(), $event->getException()->getTraceAsString());
    }

    /**
     * @param \Athena\Event\UnitTestIncomplete $event
     */
    public function addSkipped(UnitTestIncomplete $event)
    {
        $this->report->addSkipped(get_class($event->getException()), $event->getException()->getMessage(), $event->getException()->getTraceAsString());
    }

    /**
     * @param \Athena\Event\UnitTestIncomplete $event
     */
    public function addWarning(UnitTestIncomplete $event)
    {
        $this->report->addWarning(get_class($event->getException()), $event->getException()->getMessage(), $event->getException()->getTraceAsString());
    }

    /**
     * @param \Athena\Event\UnitTestIncomplete $event
     */
    public function addIncomplete(UnitTestIncomplete $event)
    {
        $this->report->addIncomplete(get_class($event->getException()), $event->getException()->getMessage(), $event->getException()->getTraceAsString());
    }

    /**
     * @param \Athena\Event\UnitTestCompleted $event
     */
    public function endTest(UnitTestCompleted $event)
    {
        $trafficLoggerFile = null;
        if ($this->trafficLogger instanceof TrafficLoggerInterface ) {
            $trafficLoggerFile = $this->trafficLogger->write();
        }

        $httpTransactions = $this->getHttpTransactionEvents();
        $this->report->endTest($event->getExecutionTime(), $trafficLoggerFile, $httpTransactions);
    }

    /**
     * @param \Athena\Event\HttpTransactionCompleted $event
     */
    public function addHttpTransaction(HttpTransactionCompleted $event)
    {
        $this->afterHttpTransactionEvents[] = $event;
    }

    /**
     * @param \Athena\Event\UnitSuiteCompleted $event
     */
    public function endTestSuite(UnitSuiteCompleted $event)
    {
        // Dodge PHP Unit strange behaviour with multiple testing suites
        if ($event->getTestSuite()->getName() == null) {
            return;
        }

        $this->report->endTestSuite();

        $this->suiteStartedCount--;
    }

    private function getHttpTransactionEvents()
    {
        $httpTransactions = [];
        if (!empty($this->afterHttpTransactionEvents)) {
            do {
                $httpRequestEvent = array_shift($this->afterHttpTransactionEvents);

                $transaction['request_method'] = $httpRequestEvent->getRequestMethod();
                $transaction['request_url'] = $httpRequestEvent->getRequestUrl();
                $transaction['request'] = utf8_encode((string) $httpRequestEvent->getRequest());
                $transaction['response'] = utf8_encode((string) $httpRequestEvent->getResponse());

                $httpTransactions[] = $transaction;
            } while (!empty($this->afterHttpTransactionEvents));
        }

        return $httpTransactions;
    }


    public function setApiVersion(string $ATHENA_ENV_API_VERSION)
    {
        $this->report->setApiVersion($ATHENA_ENV_API_VERSION);
    }

    /**
     * @inheritDoc
     */
    public function __destruct()
    {
        $contents = $this->interpreter->interpret($this->report->build()->toArray());

        $this->outputStream->write($contents);
    }
}

