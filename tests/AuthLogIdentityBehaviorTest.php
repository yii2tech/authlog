<?php

namespace yii2tech\tests\unit\authlog;

use yii2tech\authlog\AuthLogIdentityBehavior;
use yii2tech\tests\unit\authlog\data\User;

class AuthLogIdentityBehaviorTest extends TestCase
{
    public function testLogAuth()
    {
        /* @var $model User|AuthLogIdentityBehavior */
        $model = User::find()->one();

        $now = time();

        $this->assertTrue($model->logAuth(), 'Unable to write auth log');

        $authLog = $model->getAuthLogs()->one();
        $this->assertNotEmpty($authLog, 'No auth log record saved');
        $this->assertTrue($authLog->date >= $now, 'Incorrect date value');
        $this->assertEquals($model->errorDefaultValue, $authLog->error, 'Incorrect error value');
    }

    /**
     * @depends testLogAuth
     */
    public function testLogAuthOverride()
    {
        /* @var $model User|AuthLogIdentityBehavior */
        $model = User::find()->one();

        $error = 71;
        $model->logAuth(['error' => $error]);

        $authLog = $model->getAuthLogs()->one();
        $this->assertEquals($error, $authLog->error, 'Unable to override error');
    }

    /**
     * @depends testLogAuthOverride
     */
    public function testLogAuthError()
    {
        /* @var $model User|AuthLogIdentityBehavior */
        $model = User::find()->one();

        $error = 71;
        $model->logAuthError($error);

        $authLog = $model->getAuthLogs()->one();
        $this->assertEquals($error, $authLog->error, 'Unable to write error');
    }

    /**
     * @depends testLogAuth
     */
    public function testCustomDateValue()
    {
        /* @var $model User|AuthLogIdentityBehavior */
        $model = User::find()->one();

        $model->dateDefaultValue = 18;
        $model->logAuth();

        $authLog = $model->getAuthLogs()->one();
        $this->assertEquals($model->dateDefaultValue, $authLog->date, 'Incorrect date value');


        $dateDefaultValue = 81;
        $model->dateDefaultValue = function() use ($dateDefaultValue) {
            return $dateDefaultValue;
        };
        $model->logAuth();

        $authLog = $model->getAuthLogs()->orderBy(['id' => SORT_DESC])->limit(1)->one();
        $this->assertEquals($dateDefaultValue, $authLog->date, 'Incorrect date value by callback');
    }

    /**
     * @depends testLogAuth
     */
    public function testCustomErrorValue()
    {
        /* @var $model User|AuthLogIdentityBehavior */
        $model = User::find()->one();

        $model->errorDefaultValue = 56;

        $model->logAuth();

        $authLog = $model->getAuthLogs()->one();
        $this->assertEquals($model->errorDefaultValue, $authLog->error, 'Incorrect error value');
    }

    /**
     * @depends testLogAuth
     */
    public function testCustomData()
    {
        /* @var $model User|AuthLogIdentityBehavior */
        $model = User::find()->one();

        $model->defaultAuthLogData = [
            'ip' => '10.10.10.10',
            'url' => 'http://test.url',
        ];
        $model->logAuth();
        $authLog = $model->getAuthLogs()->one();

        $this->assertEquals($model->defaultAuthLogData['ip'], $authLog->ip, 'Incorrect "ip" value');
        $this->assertEquals($model->defaultAuthLogData['url'], $authLog->url, 'Incorrect "url" value');
    }

    /**
     * @depends testCustomData
     */
    public function testCustomDataCallback()
    {
        /* @var $model User|AuthLogIdentityBehavior */
        $model = User::find()->one();

        $ip = '20.20.20.20';
        $url = 'http://test.url';
        $model->defaultAuthLogData = function() use ($ip, $url) {
            return [
                'ip' => $ip,
                'url' => $url,
            ];
        };
        $model->logAuth();
        $authLog = $model->getAuthLogs()->one();

        $this->assertEquals($ip, $authLog->ip, 'Incorrect "ip" value');
        $this->assertEquals($url, $authLog->url, 'Incorrect "url" value');
    }

    /**
     * @depends testLogAuthOverride
     */
    public function testGetLastLoginDate()
    {
        /* @var $model User|AuthLogIdentityBehavior */
        $model = User::find()->one();

        $model->logAuth(['date' => 10]);
        $model->logAuth(['date' => 20]);
        $model->logAuthError(5, ['date' => 30]);

        $this->assertEquals(20, $model->getLastLoginDate());
    }

    /**
     * @depends testLogAuthError
     */
    public function testGetPreLastLoginDate()
    {
        /* @var $model User|AuthLogIdentityBehavior */
        $model = User::find()->one();

        $model->logAuth(['date' => 10]);
        $model->logAuth(['date' => 20]);
        $model->logAuth(['date' => 30]);
        $model->logAuthError(5, ['date' => 40]);

        $this->assertEquals(20, $model->getPreLastLoginDate());
    }

    /**
     * @depends testLogAuthError
     */
    public function testHasFailedLoginSequence()
    {
        /* @var $model User|AuthLogIdentityBehavior */
        $model = User::find()->one();

        $model->logAuth(['date' => 10]);
        $model->logAuthError(5, ['date' => 20]);
        $model->logAuthError(5, ['date' => 30]);
        $model->logAuthError(5, ['date' => 40]);

        $this->assertTrue($model->hasFailedLoginSequence(2));
        $this->assertTrue($model->hasFailedLoginSequence(3));
        $this->assertFalse($model->hasFailedLoginSequence(4));
        $this->assertFalse($model->hasFailedLoginSequence(5));
    }

    /**
     * @depends testLogAuth
     */
    public function testGc()
    {
        /* @var $model1 User|AuthLogIdentityBehavior */
        $model1 = new User();
        $model1->username = 'username1';
        $model1->password = 'password1';
        $model1->authKey = 'authKey1';
        $model1->save(false);
        $model1->gcProbability = 0;

        /* @var $model2 User|AuthLogIdentityBehavior */
        $model2 = new User();
        $model2->username = 'username2';
        $model2->password = 'password2';
        $model2->authKey = 'authKey2';
        $model2->save(false);
        $model1->gcProbability = 0;

        $model1->logAuth(['date' => 10]);
        $model2->logAuth(['date' => 15]);
        $model1->logAuth(['date' => 20]);
        $model2->logAuth(['date' => 25]);
        $model1->logAuth(['date' => 30]);
        $model2->logAuth(['date' => 35]);
        $model1->logAuth(['date' => 40]);
        $model2->logAuth(['date' => 45]);

        $model1->gcLimit = 2;
        $model1->gcAuthLogs(true);

        $this->assertEquals(2, $model1->getAuthLogs()->count(), 'Unable to collect garbage');
        $this->assertEquals(4, $model2->getAuthLogs()->count(), 'Extra model affected by garbage collection');
    }
}