<?php

namespace Emphloyer;

class JobWithHooks extends AbstractJob {
  public function beforeFail() {
  }

  public function beforeComplete() {
  }

  public function perform() {
  }
}

class BossTest extends \PHPUnit_Framework_TestCase {
  public function setUp() {
    $this->pipeline = $this->getMockBuilder('Emphloyer\Pipeline')
      ->disableOriginalConstructor()
      ->getMock();
    $this->scheduler = $this->getMockBuilder('Emphloyer\Scheduler')
      ->disableOriginalConstructor()
      ->getMock();
    $this->boss = new Boss($this->pipeline, $this->scheduler);
  }

  public function testScheduleWorkDoesNothingWhenThereIsNoScheduler() {
    $boss = new Boss($this->pipeline);
    $this->pipeline->expects($this->never())
      ->method('enqueue');

    $boss->scheduleWork();
  }

  public function testScheduleWorkEnqueuesJobsReturnedByScheduler() {
    $job1 = $this->getMock('Emphloyer\Job');
    $job2 = $this->getMock('Emphloyer\Job');
    $jobs = array($job1, $job2);

    $this->scheduler->expects($this->once())
      ->method('getJobsFor')
      ->will($this->returnValue($jobs));

    $this->pipeline->expects($this->at(0))
      ->method('enqueue')
      ->with($job1);

    $this->pipeline->expects($this->at(1))
      ->method('enqueue')
      ->with($job2);

    $this->boss->scheduleWork();
  }

  public function testGetEmployees() {
    $this->boss->allocateEmployee(new Employee());
    $this->boss->allocateEmployee(new Employee());
    $this->boss->allocateEmployee(new Employee());
    $employees = $this->boss->getEmployees();
    $this->assertEquals(3, count($employees));
    foreach ($employees as $employee) {
      $this->assertInstanceOf('Emphloyer\Employee', $employee);
    }
  }

  public function testGetWorkReturnsJobFromPipeline() {
    $options = array('only' => array('special'));
    $employee = new Employee($options);
    $job = $this->getMock('Emphloyer\Job');
    $this->pipeline->expects($this->once())
      ->method('dequeue')
      ->with($options)
      ->will($this->returnValue($job));
    $this->assertEquals($job, $this->boss->getWork($employee));
  }

  public function testGetWorkReturnsNullWhenThereIsNoWork() {
    $employee = new Employee();
    $this->pipeline->expects($this->once())
      ->method('dequeue')
      ->with(array())
      ->will($this->returnValue(null));
    $this->assertNull($this->boss->getWork($employee));
  }

  public function testDelegateWorkDelegatesToAvailableEmployees() {
    $employee1 = $this->getMock('Emphloyer\Employee');
    $employee2 = $this->getMock('Emphloyer\Employee');
    $employee3 = $this->getMock('Emphloyer\Employee');
    $this->boss->allocateEmployee($employee1);
    $this->boss->allocateEmployee($employee2);

    $employee1->expects($this->any())
      ->method('isFree')
      ->will($this->returnValue(false));

    $job = $this->getMock('Emphloyer\Job');
    $employee2->expects($this->any())
      ->method('isFree')
      ->will($this->returnValue(true));
    $employee2->expects($this->any())
      ->method('getOptions')
      ->will($this->returnValue(array('only' => array('special'))));
    $employee2->expects($this->once())
      ->method('work')
      ->with($job);

    $this->pipeline->expects($this->once())
      ->method('dequeue')
      ->with(array('only' => array('special')))
      ->will($this->returnValue($job));

    $this->pipeline->expects($this->once())
      ->method('reconnect');

    $this->boss->delegateWork();
  }

  public function testDelegateWorkDoesNothingWhenNoEmployeeAvailable() {
    $employee = $this->getMock('Emphloyer\Employee');
    $this->boss->allocateEmployee($employee);

    $employee->expects($this->any())
      ->method('isFree')
      ->will($this->returnValue(false));

    $this->pipeline->expects($this->never())
      ->method('dequeue');
    $this->boss->delegateWork();
  }

  public function testDelegateWorkDoesNothingWhenNoJobAvailable() {
    $employee = $this->getMock('Emphloyer\Employee');
    $this->boss->allocateEmployee($employee);

    $employee->expects($this->any())
      ->method('isFree')
      ->will($this->returnValue(true));

    $employee->expects($this->any())
      ->method('getOptions')
      ->will($this->returnValue(array()));

    $this->pipeline->expects($this->once())
      ->method('dequeue')
      ->with(array())
      ->will($this->returnValue(null));

    $employee->expects($this->never())
      ->method('work');

    $this->boss->delegateWork();
  }

  public function testWaitOnEmployees() {
    $employee1 = $this->getMock('Emphloyer\Employee');
    $employee2 = $this->getMock('Emphloyer\Employee');
    $this->boss->allocateEmployee($employee1);
    $this->boss->allocateEmployee($employee2);

    $employee1->expects($this->once())
      ->method('isBusy')
      ->will($this->returnValue(false));

    $employee2->expects($this->once())
      ->method('isBusy')
      ->will($this->returnValue(true));
    $employee2->expects($this->once())
      ->method('getWorkState')
      ->with(true);

    $this->boss->waitOnEmployees();
  }

  public function testStopEmployees() {
    $employee1 = $this->getMock('Emphloyer\Employee');
    $employee2 = $this->getMock('Emphloyer\Employee');
    $this->boss->allocateEmployee($employee1);
    $this->boss->allocateEmployee($employee2);

    $employee1->expects($this->once())
      ->method('isBusy')
      ->will($this->returnValue(false));

    $employee2->expects($this->once())
      ->method('isBusy')
      ->will($this->returnValue(true));
    $employee2->expects($this->once())
      ->method('stop');

    $this->boss->stopEmployees();
  }

  public function testUpdateProgressWithFreeEmployee() {
    $freeEmployee = $this->getMock('Emphloyer\Employee');
    $this->boss->allocateEmployee($freeEmployee);

    $freeEmployee->expects($this->once())
      ->method('isFree')
      ->will($this->returnValue(true));

    $this->boss->updateProgress();
  }

  public function testUpdateProgressWithBusyEmployee() {
    $busyEmployee = $this->getMock('Emphloyer\Employee');
    $this->boss->allocateEmployee($busyEmployee);

    $busyEmployee->expects($this->once())
      ->method('isBusy')
      ->will($this->returnValue(true));

    $this->boss->updateProgress();
  }

  public function testUpdateProgressWithCompletedEmployee() {
    $completedEmployee = $this->getMock('Emphloyer\Employee');
    $this->boss->allocateEmployee($completedEmployee);

    $completedEmployee->expects($this->once())
      ->method('isFree')
      ->will($this->returnValue(false));
    $completedEmployee->expects($this->once())
      ->method('isBusy')
      ->will($this->returnValue(false));
    $completedEmployee->expects($this->once())
      ->method('getWorkState')
      ->will($this->returnValue(Employee::COMPLETE));
    $completedJob = $this->getMock('Emphloyer\Job');
    $completedEmployee->expects($this->once())
      ->method('getJob')
      ->will($this->returnValue($completedJob));
    $completedJob->expects($this->never())
      ->method('beforeComplete');
    $this->pipeline->expects($this->once())
      ->method('complete')
      ->with($completedJob);
    $completedEmployee->expects($this->once())
      ->method('free');

    $this->boss->updateProgress();
  }

  public function testUpdateProgressWithCompletedEmployeeWithJobThatHasHook() {
    $completedEmployee = $this->getMock('Emphloyer\Employee');
    $this->boss->allocateEmployee($completedEmployee);

    $completedEmployee->expects($this->once())
      ->method('isFree')
      ->will($this->returnValue(false));
    $completedEmployee->expects($this->once())
      ->method('isBusy')
      ->will($this->returnValue(false));
    $completedEmployee->expects($this->once())
      ->method('getWorkState')
      ->will($this->returnValue(Employee::COMPLETE));
    $completedJob = $this->getMock('Emphloyer\JobWithHooks');
    $completedEmployee->expects($this->once())
      ->method('getJob')
      ->will($this->returnValue($completedJob));
    $completedJob->expects($this->once())
      ->method('beforeComplete');
    $this->pipeline->expects($this->once())
      ->method('complete')
      ->with($completedJob);
    $completedEmployee->expects($this->once())
      ->method('free');

    $this->boss->updateProgress();
  }

  public function testUpdateProgressWithFailedJobThatMayNotBeRetried() {
    $failedEmployee = $this->getMock('Emphloyer\Employee');
    $this->boss->allocateEmployee($failedEmployee);

    $failedEmployee->expects($this->once())
      ->method('isFree')
      ->will($this->returnValue(false));
    $failedEmployee->expects($this->once())
      ->method('isBusy')
      ->will($this->returnValue(false));
    $failedEmployee->expects($this->once())
      ->method('getWorkState')
      ->will($this->returnValue(Employee::FAILED));
    $failedJob = $this->getMock('Emphloyer\Job');
    $failedEmployee->expects($this->once())
      ->method('getJob')
      ->will($this->returnValue($failedJob));
    $this->pipeline->expects($this->once())
      ->method('fail')
      ->with($failedJob);
    $failedEmployee->expects($this->once())
      ->method('free');

    $this->boss->updateProgress();
  }

  public function testUpdateProgressWithFailedJobThatMayBeRetried() {
    $failedEmployeeWithRetryableJob = $this->getMock('Emphloyer\Employee');
    $this->boss->allocateEmployee($failedEmployeeWithRetryableJob);

    $failedEmployeeWithRetryableJob->expects($this->once())
      ->method('isFree')
      ->will($this->returnValue(false));
    $failedEmployeeWithRetryableJob->expects($this->once())
      ->method('isBusy')
      ->will($this->returnValue(false));
    $failedEmployeeWithRetryableJob->expects($this->once())
      ->method('getWorkState')
      ->will($this->returnValue(Employee::FAILED));
    $retryableJob = $this->getMock('Emphloyer\Job');
    $failedEmployeeWithRetryableJob->expects($this->once())
      ->method('getJob')
      ->will($this->returnValue($retryableJob));
    $retryableJob->expects($this->never())
      ->method('beforeFail');
    $retryableJob->expects($this->once())
      ->method('mayTryAgain')
      ->will($this->returnValue(true));
    $this->pipeline->expects($this->once())
      ->method('reset')
      ->with($retryableJob);
    $failedEmployeeWithRetryableJob->expects($this->once())
      ->method('free');

    $this->boss->updateProgress();
  }

  public function testUpdateProgressWithFailedJobThatHasOnFailHook() {
    $failedEmployeeWithRetryableJob = $this->getMock('Emphloyer\Employee');
    $this->boss->allocateEmployee($failedEmployeeWithRetryableJob);

    $failedEmployeeWithRetryableJob->expects($this->once())
      ->method('isFree')
      ->will($this->returnValue(false));
    $failedEmployeeWithRetryableJob->expects($this->once())
      ->method('isBusy')
      ->will($this->returnValue(false));
    $failedEmployeeWithRetryableJob->expects($this->once())
      ->method('getWorkState')
      ->will($this->returnValue(Employee::FAILED));
    $retryableJob = $this->getMock('Emphloyer\JobWithHooks');
    $failedEmployeeWithRetryableJob->expects($this->once())
      ->method('getJob')
      ->will($this->returnValue($retryableJob));
    $retryableJob->expects($this->once())
      ->method('beforeFail');
    $retryableJob->expects($this->once())
      ->method('mayTryAgain')
      ->will($this->returnValue(true));
    $this->pipeline->expects($this->once())
      ->method('reset')
      ->with($retryableJob);
    $failedEmployeeWithRetryableJob->expects($this->once())
      ->method('free');

    $this->boss->updateProgress();
  }
}
