<?php

namespace Emphloyer;

class PipelineTestJob extends AbstractJob {
  public function __construct($name = "", $again = false) {
    $this->attributes['name'] = $name;
    $this->attributes['try_again'] = $again;
  }

  public function mayTryAgain() {
    if ($this->attributes['try_again'] === true) {
      return true;
    }
  }

  public function perform() {
  }
}

class PipelineTest extends \PHPUnit_Framework_TestCase {
  public function setUp() {
    $this->backend = $this->getMock('\Emphloyer\Pipeline\Backend');
    $this->pipeline = new Pipeline($this->backend);
  }

  public function testReconnect() {
    $this->backend->expects($this->once())
      ->method('reconnect');
    $this->pipeline->reconnect();
  }

  public function testEnqueueSerializesAndPassesToBackend() {
    $job = new PipelineTestJob("Mark");
    $this->backend->expects($this->once())
      ->method('enqueue')
      ->with($this->equalTo(array('className' => 'Emphloyer\PipelineTestJob', 'name' => 'Mark', 'try_again' => false, 'type' => 'job')))
      ->will($this->returnValue(array('className' => 'Emphloyer\PipelineTestJob', 'name' => 'Mark', 'try_again' => false, 'id' => 1, 'type' => 'job')));
     
    $savedJob = $this->pipeline->enqueue($job);
    $this->assertInstanceOf('Emphloyer\PipelineTestJob', $savedJob);
    $this->assertEquals(1, $savedJob->getId());
  }

  public function testFindInstantiatesJobFromBackendAttributes() {
    $this->backend->expects($this->once())
      ->method('find')
      ->with(2)
      ->will($this->returnValue(array('className' => 'Emphloyer\PipelineTestJob', 'name' => 'Kremer', 'try_again' => false, 'id' => 2, 'type' => 'special')));
    $job = $this->pipeline->find(2);
    $this->assertInstanceOf('Emphloyer\PipelineTestJob', $job);
    $this->assertEquals(array('name' => 'Kremer', 'try_again' => false, 'id' => 2), $job->getAttributes());
    $this->assertEquals('special', $job->getType());
  }

  public function testFindReturnsNullWhenBackendReturnsNull() {
    $this->backend->expects($this->once())
      ->method('find')
      ->with(3)
      ->will($this->returnValue(null));
    $job = $this->pipeline->find(3);
    $this->assertNull($job);
  }

  public function testDequeueInstantiatesJobFromBackendAttributes() {
    $this->backend->expects($this->once())
      ->method('dequeue')
      ->will($this->returnValue(array('className' => 'Emphloyer\PipelineTestJob', 'name' => 'Mark', 'try_again' => false, 'type' => 'x')));
    $job = $this->pipeline->dequeue();
    $this->assertInstanceOf('Emphloyer\PipelineTestJob', $job);
    $this->assertEquals(array('name' => 'Mark', 'try_again' => false), $job->getAttributes());
    $this->assertEquals('x', $job->getType());
  }

  public function testDequeueReturnsNullWhenBackendReturnsNull() {
    $this->backend->expects($this->once())
      ->method('dequeue')
      ->will($this->returnValue(null));
    $job = $this->pipeline->dequeue();
    $this->assertNull($job);
  }

  public function testReset() {
    $job = new PipelineTestJob("Mark");
    $this->backend->expects($this->once())
      ->method('reset')
      ->with($this->equalTo(array('className' => 'Emphloyer\PipelineTestJob', 'name' => 'Mark', 'try_again' => false, 'type' => 'job')));
    $this->pipeline->reset($job);
  }

  public function testComplete() {
    $job = new PipelineTestJob("Mark");
    $this->backend->expects($this->once())
      ->method('complete')
      ->with($this->equalTo(array('className' => 'Emphloyer\PipelineTestJob', 'name' => 'Mark', 'try_again' => false, 'type' => 'job')));
    $this->pipeline->complete($job);
  }

  public function testFail() {
    $job = new PipelineTestJob("Mark");
    $this->backend->expects($this->once())
      ->method('fail')
      ->with($this->equalTo(array('className' => 'Emphloyer\PipelineTestJob', 'name' => 'Mark', 'try_again' => false, 'type' => 'job')));
    $this->pipeline->fail($job);
  }

  public function testClearClearsTheBackend() {
    $this->backend->expects($this->once())
      ->method('clear');
    $this->pipeline->clear();
  }
}
