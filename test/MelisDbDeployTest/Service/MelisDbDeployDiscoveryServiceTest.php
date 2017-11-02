<?php

/**
 * Melis Technology (http://www.melistechnology.com)
 *
 * @copyright Copyright (c) 2017 Melis Technology (http://www.melistechnology.com)
 *
 */
namespace MelisDbDeployTest\Service;

use PHPUnit\Framework\TestCase;
use Zend\ServiceManager\ServiceManager;

class MelisDbDeployDiscoveryServiceTest extends TestCase
{
    /**
     * @var \MelisDbDeploy\Service\MelisDbDeployDiscoveryService
     */
    protected $instance;

    /**
     * @var \Composer\Composer
     */
    protected $composer;

    /**
     * @var \phpmock\Mock
     */
    protected $mockGetLocalPackages;

    public function setUp()
    {
        $this->instance = new \MelisDbDeploy\Service\MelisDbDeployDiscoveryService();
        $this->composer = new \Composer\Composer();

        $configMock = $this->getMockBuilder('\Composer\Config')->getMock();
        $configMock->expects($this->any())
            ->method('get')
            ->will($this->returnValue('foo/bar'))
        ;

        $this->composer->setConfig($configMock);

        $builder = new \phpmock\MockBuilder();
        $builder->setNamespace('\MelisDbDeploy\Service\MelisDbDeployDiscoveryService')
            ->setName("getLocalPackages")
            ->setFunction(function () {
                eval(\Psy\sh());
                return [];
            })
        ;
        $this->mockGetLocalPackages = $builder->build();
        $this->mockGetLocalPackages->enable();

        /*$repositoryManager = new Repository\RepositoryManager();

        $localRepository = $this->getMockBuilder('\Composer\Repository\WritableRepositoryInterface')->getMock();
        $repositoryManagerMock = $this->getMockBuilder('\Composer\Repository\RepositoryManager')->getMock();
        $configMock->expects($this->any())
            ->method('getLocalRepository')
            ->will($this->returnValue($localRepository))
        ;

        $this->composer->setConfig($configMock);
        $this->composer->setRepositoryManager($repositoryManagerMock);*/
    }

    public function tearDown()
    {
        $this->mockGetLocalPackages->disable();
    }

    public function testProcessing()
    {
        $installCalled = false;
        $applyDeltaPathsCalled = false;
        $serviceManager = new ServiceManager();

        $deployServiceMock = $this->getMockBuilder('\MelisDbDeploy\Service\MelisDbDeployDeployService')->getMock();
        $deployServiceMock->expects($this->any())
            ->method('isInstalled')
            ->will($this->returnValue(false))
        ;
        $deployServiceMock->expects($this->any())
            ->method('install')
            ->will($this->returnValue(function() use(&$installCalled) {
                $installCalled = true;
            }))
        ;
        $deployServiceMock->expects($this->any())
            ->method('applyDeltaPaths')
            ->will($this->returnValue(function() use(&$applyDeltaPathsCalled) {
                $applyDeltaPathsCalled = true;
            }))
        ;

        $serviceManager->setService('MelisDbDeployDeployService', $deployServiceMock);
        $this->instance->setServiceLocator($serviceManager);

        $this->instance->processing($this->composer);

        $this->assertEquals(true, $installCalled);
        $this->assertEquals(false, $applyDeltaPathsCalled);
    }
}