<?php
namespace Athena\Event\Subscriber;

use Athena\Event\HttpTransactionCompleted;
use Athena\Event\Proxy\BehatProxy;
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
     * @var BddReportBuilder
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
     * @var string
     */
    private $currentOutlineTitle;

    /**
     * @var HttpTransactionCompleted[]
     */
    private $afterHttpTransactionEvents = [];

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
        $events = BehatProxy::getSubscribedEvents();
        $events[HttpTransactionCompleted::AFTER] = 'afterComplete';

        return $events;
    }

    /**
     * @param \Athena\Event\HttpTransactionCompleted $event
     */
    public function afterComplete(HttpTransactionCompleted $event)
    {
        $this->report->addHttpTransaction($event->getRequest(), $event->getResponse());
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
     * @inheritDoc
     */
    public function __destruct()
    {
        $contents = $this->interpreter->interpret($this->report->build()->toArray());

        $this->outputStream->write($contents);
    }
}

