<?php

namespace yii2tech\tests\unit\authlog;

use yii2tech\authlog\AuthLogLoginFormBehavior;
use yii2tech\tests\unit\authlog\data\LoginForm;
use yii2tech\tests\unit\authlog\data\User;

class AuthLogLoginFormBehaviorTest extends TestCase
{
    public function testSetupIdentity()
    {
        $behavior = new AuthLogLoginFormBehavior();

        $identity = new User();
        $behavior->setIdentity($identity);
        $this->assertEquals($identity, $behavior->getIdentity());
    }

    /**
     * @depends testSetupIdentity
     */
    public function testFindIdentityDefault()
    {
        /* @var $loginForm LoginForm|AuthLogLoginFormBehavior */
        $loginForm = new LoginForm();

        $user = User::find()->limit(1)->one();
        $loginForm->username = $user->username;

        $identity = $loginForm->getIdentity();

        $this->assertNotEmpty($identity);
        $this->assertEquals($user->id, $identity->id);
    }

    /**
     * @depends testSetupIdentity
     */
    public function testFindIdentityMethod()
    {
        /* @var $loginForm LoginForm|AuthLogLoginFormBehavior */
        $loginForm = new LoginForm();

        $loginForm->findIdentity = 'customFindIdentity';

        $identity = $loginForm->getIdentity();

        $this->assertNotEmpty($identity);
        $this->assertEquals('method', $identity->username);
    }

    /**
     * @depends testSetupIdentity
     */
    public function testFindIdentityCallback()
    {
        /* @var $loginForm LoginForm|AuthLogLoginFormBehavior */
        $loginForm = new LoginForm();

        $loginForm->findIdentity = function() {
            $identity = new User();
            $identity->username = 'callback';
            return $identity;
        };

        $identity = $loginForm->getIdentity();

        $this->assertNotEmpty($identity);
        $this->assertEquals('callback', $identity->username);
    }

    /**
     * @depends testSetupIdentity
     */
    public function testVerifyRobot()
    {
        /* @var $loginForm LoginForm|AuthLogLoginFormBehavior */
        $loginForm = new LoginForm();
        $loginForm->setIdentity(new User());

        $loginForm->validate();
        $this->assertFalse($loginForm->hasErrors('verifyCode'));

        $loginForm->setIsVerifyRobotRequired(true);
        $loginForm->validate();
        $this->assertTrue($loginForm->hasErrors('verifyCode'));
    }

    /**
     * @depends testSetupIdentity
     */
    public function testFindIsVerifyRobotRequired()
    {
        /* @var $loginForm LoginForm|AuthLogLoginFormBehavior */
        $user = User::find()->limit(1)->one();

        $loginForm = new LoginForm();
        $loginForm->setIdentity($user);

        $this->assertFalse($loginForm->getIsVerifyRobotRequired());

        $loginForm = new LoginForm();
        $loginForm->setIdentity($user);
        $loginForm->logAuthError('1');
        $loginForm->logAuthError('2');

        $this->assertTrue($loginForm->getIsVerifyRobotRequired());
    }

    /**
     * @depends testFindIdentityDefault
     */
    public function testLogAuthErrorOnValidation()
    {
        /* @var $loginForm LoginForm|AuthLogLoginFormBehavior */
        /* @var $user User */
        $user = User::find()->limit(1)->one();

        $loginForm = new LoginForm();
        $loginForm->username = $user->username;

        $loginForm->validate();
        $this->assertEquals(0, $user->getAuthLogs()->count());

        $loginForm->password = 'wrong password';
        $loginForm->validate();
        $this->assertEquals(1, $user->getAuthLogs()->count());
    }

    /**
     * @depends testLogAuthErrorOnValidation
     */
    public function testDeactivateIdentity()
    {
        /* @var $loginForm LoginForm|AuthLogLoginFormBehavior */
        /* @var $user User */
        $user = User::find()->limit(1)->one();

        $loginForm = new LoginForm();
        $loginForm->username = $user->username;
        $loginForm->password = 'wrong password';
        $loginForm->deactivateIdentity = 'deactivate';

        $loginForm->validate();
        $loginForm->validate();
        $loginForm->validate();

        $user->refresh();
        $this->assertEquals('inactive', $user->authKey);
    }

    /**
     * @depends testDeactivateIdentity
     */
    public function testDeactivateIdentityCallback()
    {
        /* @var $loginForm LoginForm|AuthLogLoginFormBehavior */
        /* @var $user User */
        $user = User::find()->limit(1)->one();

        $loginForm = new LoginForm();
        $loginForm->deactivateIdentity = function ($identity) {
            /* @var $identity User */
            $identity->updateAttributes(['authKey' => 'callback']);
        };
        $loginForm->username = $user->username;
        $loginForm->password = 'wrong password';

        $loginForm->validate();
        $loginForm->validate();
        $loginForm->validate();

        $user->refresh();
        $this->assertEquals('callback', $user->authKey);
    }
}