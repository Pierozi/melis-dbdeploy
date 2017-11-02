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
        chdir('/var/www/html/cache');

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
        $this->db = new Adapter([
            'driver'   => 'Mysqli',
            'database' => 'melis',
            'hostname' => 'mysql',
            'username' => 'root',
            'password' => 'rootpasswd',
        ]);
    }
}