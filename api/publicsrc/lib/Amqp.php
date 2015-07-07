<?php
namespace publicsrc\lib;

use AMQPConnection;
use AMQPChannel;
use AMQPExchange;

class Amqp
{
    private $amqpConnection;

    public function __construct($host)
    {
        $this->amqpConnection = new AMQPConnection();
        $this->amqpConnection->setLogin("guest");
        $this->amqpConnection->setPassword("guest");
        $this->amqpConnection->setHost($host);
        $this->amqpConnection->connect();

        if(!$this->amqpConnection->isConnected()) {
            die("Cannot connect to the broker, exiting !\n");
        }
    }

    public function recv($exchange_name, $queueName,$process_callback)
    {

        $channel = new AMQPChannel($this->amqpConnection);
        $channel->setPrefetchCount(1);
        $queue = new AMQPQueue($channel);
        $queue->setName($queueName);
        $queue->setFlags(AMQP_DURABLE);
        $queue->declareQueue();
        $queue->bind($exchange_name, $queueName);
        $queue->consume($process_callback);

        if(!$this->amqpConnection->disconnect()) {
            throw new Exception("Could not disconnect !");
        }
    }

    public function subscribe($topic,$queueName,$process_callback)
    {
        $channel = new AMQPChannel($this->amqpConnection);
        $channel->setPrefetchCount(0);
        $queue = new AMQPQueue($channel);
        $queue->setName($queueName);
        $queue->setFlags(AMQP_DURABLE);
        $queue->declareQueue();
        $queue->bind('topic', $topic);
        $queue->consume($process_callback);

        if(!$this->amqpConnection->disconnect()) {
            throw new Exception("Could not disconnect !");
        }
    }

    public function send($exchange_name,$text)
    {
        $channel = new AMQPChannel($this->amqpConnection);
        $exchange = new AMQPExchange($channel);
        $exchange->setName($exchange_name);
        $exchange->setType('fanout');
        $exchange->setFlags(AMQP_DURABLE);
        $exchange->declareExchange();
        $message = $exchange->publish($text, "");
        if(!$message) {
            echo 'message send error';
            return -10001;
        }
        $this->amqpConnection->disconnect();
        return 0;
    }

    public function publish($route_key,$text)
    {
        $channel = new AMQPChannel($this->amqpConnection);
        $exchange = new AMQPExchange($channel);
        $exchange->setName('topic');
        $exchange->setType('topic');
        $exchange->declare();
        $message = $exchange->publish($text, $route_key);
        if (!$this->amqpConnection->disconnect()) {
            throw new Exception("Could not disconnect !");
        }
    }

    public function close()
    {
        if (!$this->amqpConnection->disconnect()) {
            throw new Exception("Could not disconnect !");
        }
    }
}

