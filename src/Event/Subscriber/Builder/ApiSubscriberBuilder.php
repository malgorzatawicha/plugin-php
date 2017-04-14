<?php
namespace Athena\Event\Subscriber\Builder;

use Athena\Athena;
use Athena\Event\Subscriber\ApiSubscriber;
use Athena\Logger\Interpreter\DelimitedJsonInterpreter;
use Athena\Logger\ProxyTrafficLogger;
use Athena\Logger\Timer\MicroTimer;
use Athena\Stream\NamedPipeOutputStream;
use Athena\Stream\UniqueNameFileOutputStream;

class ApiSubscriberBuilder extends AbstractSubscriberBuilder
{
    /**
     * @return \Athena\Event\Subscriber\ApiSubscriber
     */
    public function build()
    {
        $interpreter  = new DelimitedJsonInterpreter(DelimitedJsonInterpreter::NEW_LINE);
        $outputStream = new NamedPipeOutputStream(ATHENA_REPORT_PIPE_NAME);
        $timer        = new MicroTimer();

        $subscriber = new ApiSubscriber($interpreter, $outputStream, $timer);

        if ($this->withTrafficLogger) {
            $subscriber->setTrafficLogger(
                new ProxyTrafficLogger(Athena::proxy(), new UniqueNameFileOutputStream($this->outputPathName, 'har')));
        }

        if (true) { //!is_null($_ENV["ATHENA_ENV_API_VERSION"])) {
            $subscriber->setApiVersion('test');//$_ENV["ATHENA_ENV_API_VERSION"]);
        }

        return $subscriber;
    }
}

