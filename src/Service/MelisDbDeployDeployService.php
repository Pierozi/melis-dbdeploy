<?php

/**
 * Melis Technology (http://www.melistechnology.com)
 *
 * @copyright Copyright (c) 2017 Melis Technology (http://www.melistechnology.com)
 *
 */

namespace MelisDbDeploy\Service;

use MelisCore\Service\MelisCoreGeneralService;
use Zend\Db\Adapter\Adapter;
use Zend\Db\Adapter\Exception\InvalidQueryException;

class MelisDbDeployDeployService extends MelisCoreGeneralService
{
    /**
     *
     * The tablename to use from the database for storing all changes
     * This cannot be changed due to Phing Task Dependencie
     *
     * @var string
     */
    const TABLE_NAME = 'changelog';

    const OUTPUT_FILENAME = 'melisplatform-dbdeploy.sql';
    const OUTPUT_FILENAME_UNDO = 'melisplatform-dbdeploy-reverse.sql';

    const DRIVER = 'pdo';

    /**
     * @var Adapter
     */
    protected $db;

    public function __construct()
    {
        $this->prepare();
    }

    public function isInstalled()
    {
        try {
            $this->db->query(
                'describe ' . self::TABLE_NAME,
                Adapter::QUERY_MODE_EXECUTE
            );
        } catch (InvalidQueryException $invalidQueryException) {
            return false;
        }

        return true;
    }

    public function install()
    {
        $sqlCreateTableChangelog = file_get_contents(dirname(dirname(__DIR__))
            . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR
            . 'changelog.sql'
        );
        $this->db->query($sqlCreateTableChangelog, Adapter::QUERY_MODE_EXECUTE);
    }

    public function applyDeltaPaths(Array $deltaPaths)
    {
        $cwd = getcwd();
        $workingDirectory = $cwd . DIRECTORY_SEPARATOR . 'cache';

        chdir($workingDirectory);

        if (!file_exists($workingDirectory)) {
            throw new \Exception(sprintf(
                'The directory %s must exist to store temporary database migration file',
                $workingDirectory
            ));
        }

        $this->begin();

        try {
            foreach ($deltaPaths as $deltaPath) {
                $this->execute($deltaPath);
            }
        } catch (\Exception $exception) {
            $this->rollback();
            throw $exception;
        }

        $this->commit();

        chdir($cwd);
    }

    protected function begin()
    {
        $project = new \Project();
        $DbDeployTask = new \DbDeployTask();

        $DbDeployTask->setProject($project);
        $DbDeployTask->setUrl('mysql:host=mysql;dbname=melis');
        $DbDeployTask->setUserId('root');
        $DbDeployTask->setPassword('rootpasswd');
        $DbDeployTask->setOutputFile(static::OUTPUT_FILENAME);
        $DbDeployTask->setUndoOutputFile(static::OUTPUT_FILENAME_UNDO);

        $this->db
            ->getDriver()
            ->getConnection()
            ->beginTransaction()
        ;
    }

    protected function execute($path)
    {
        $DbDeployTask->setDir($path);
        $DbDeployTask->main();

        $sql = file_get_contents(static::OUTPUT_FILENAME);

        $this->db->query($sql, Adapter::QUERY_MODE_EXECUTE);

        rename(static::OUTPUT_FILENAME, microtime(true) . '-' . static::OUTPUT_FILENAME);
    }

    protected function commit()
    {
        $this->db
            ->getDriver()
            ->getConnection()
            ->commit()
        ;
    }

    protected function rollback()
    {
        $this->db
            ->getDriver()
            ->getConnection()
            ->rollback()
        ;
    }

    protected function prepare()
    {
        $path = 'config/autoload/platforms/development.php';

        if (!file_exists($path)) {
            throw new \Exception(sprintf(
                'The configuration file %s must exist to be able to connect on the database',
                $path
            ));
        }

        $appConfig = include $path;

        $this->db = new Adapter($appConfig['db'] + [
            'driver' => static::DRIVER,
        ]);
    }
}