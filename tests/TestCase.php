<?php

namespace yii2tech\tests\unit\authlog;

use yii\helpers\ArrayHelper;
use Yii;

/**
 * Base class for the test cases.
 */
class TestCase extends \PHPUnit_Framework_TestCase
{
    protected function setUp()
    {
        parent::setUp();
        $this->mockApplication();

        $this->setupTestDbData();
    }

    protected function tearDown()
    {
        parent::tearDown();
        $this->destroyApplication();
    }

    /**
     * Populates Yii::$app with a new application
     * The application will be destroyed on tearDown() automatically.
     * @param array $config The application configuration, if needed
     * @param string $appClass name of the application class to create
     */
    protected function mockApplication($config = [], $appClass = '\yii\console\Application')
    {
        new $appClass(ArrayHelper::merge([
            'id' => 'testapp',
            'basePath' => __DIR__,
            'vendorPath' => $this->getVendorPath(),
            'components' => [
                'request' => [
                    'class' => __NAMESPACE__ . '\data\Request'
                ],
                'db' => [
                    'class' => 'yii\db\Connection',
                    'dsn' => 'sqlite::memory:',
                ],
            ],
        ], $config));
    }

    /**
     * @return string vendor path
     */
    protected function getVendorPath()
    {
        return dirname(__DIR__) . '/vendor';
    }

    /**
     * Destroys application in Yii::$app by setting it to null.
     */
    protected function destroyApplication()
    {
        Yii::$app = null;
    }

    /**
     * Setup tables for test ActiveRecord
     */
    protected function setupTestDbData()
    {
        $db = Yii::$app->getDb();

        // Structure :

        $table = 'User';
        $columns = [
            'id' => 'pk',
            'username' => 'string',
            'password' => 'string',
            'authKey' => 'string',
        ];
        $db->createCommand()->createTable($table, $columns)->execute();

        $table = 'AuthLog';
        $columns = [
            'id' => 'pk',
            'userId' => 'integer',
            'date' => 'integer',
            'error' => 'integer',
            'ip' => 'string',
            'host' => 'string',
            'url' => 'string',
        ];
        $db->createCommand()->createTable($table, $columns)->execute();

        // Data :

        $db->createCommand()->batchInsert('User', ['username', 'password', 'authKey'], [
            ['test_name', 'test_passsword', 'test_auth_key'],
        ])->execute();
    }
}
