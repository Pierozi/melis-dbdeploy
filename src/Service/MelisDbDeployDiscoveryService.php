<?php

/**
 * Melis Technology (http://www.melistechnology.com)
 *
 * @copyright Copyright (c) 2017 Melis Technology (http://www.melistechnology.com)
 *
 */

namespace MelisDbDeploy\Service;

use Composer\Composer;
use Composer\Package\PackageInterface;
use MelisCore\Service\MelisCoreGeneralService;

class MelisDbDeployDiscoveryService extends MelisCoreGeneralService
{
    const VENDOR = 'melisplatform';

    /**
     * @var Composer
     */
    protected $Composer;

    /**
     * Processing all Melis Platform Modules that neeed upgrade database
     * @param Composer $composer
     */
    public function processing(Composer $composer)
    {
        $this->Composer = $composer;
        /** @var MelisDbDeployDeployService $deployService */
        $deployService = $this->getServiceLocator()->get('MelisDbDeployDeployService');

        if (false === $deployService->isInstalled()) {
            $deployService->install();
        }

        $deltas = $this->findDeltaPaths();

        if (!empty($deltas)) {
            $deployService->applyDeltaPaths($deltas);
        }
    }

    /**
     * Find melis delta migration that match
     * condition of extra dbdeploy
     */
    protected function findDeltaPaths()
    {
        $vendorDir = $this->Composer->getConfig()->get('vendor-dir');
        $packages = $this->getLocalPackages();
        $deltas = [];

        foreach ($packages as $package) {
            $vendor = explode('/', $package->getName(), 2);

            if (empty($vendor) || static::VENDOR !== $vendor[0]) {
                continue;
            }

            $extra = $package->getExtra();

            if (!in_array('dbdeploy', $extra) || true !== $extra['dbdeploy']) {
                continue;
            }

            $deltas += static::findDeltasInPackage($package, $vendorDir);
        }

        return $deltas;
    }

    /**
     * @return \Composer\Package\PackageInterface[]
     */
    protected function getLocalPackages()
    {
        return $this->Composer
            ->getRepositoryManager()
            ->getLocalRepository()
            ->getCanonicalPackages()
        ;
    }

    /**
     * @param PackageInterface $package
     * @param $vendorDir
     * @return array
     */
    protected static function findDeltasInPackage(PackageInterface $package, $vendorDir)
    {
        $sp = DIRECTORY_SEPARATOR;
        $path = $vendorDir . $sp . $package->getName() . $sp . 'install';

        if (false === file_exists($path)) {
            return [];
        }

        $files = [];

        foreach (glob("$path/*.sql") as $filename) {
            $files[] = $filename;
        }

        return $files;
    }
}