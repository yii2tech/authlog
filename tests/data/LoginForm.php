<?php

namespace yii2tech\tests\unit\authlog\data;

use yii\base\Model;
use yii2tech\authlog\AuthLogLoginFormBehavior;

class LoginForm extends Model
{
    public $username;
    public $password;
    public $verifyCode;

    /**
     * {@inheritdoc}
     */
    public function behaviors()
    {
        return [
            'authLog' => [
                'class' => AuthLogLoginFormBehavior::className(),
                'verifyRobotAttribute' => 'verifyCode',
                'verifyRobotRule' => ['required'],
                'verifyRobotFailedLoginSequence' => 2,
                'deactivateFailedLoginSequence' => 3,
            ],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['username', 'password'], 'required'],
            ['password', 'validatePassword'],
        ];
    }

    /**
     * Validates the password.
     * @param string $attribute the attribute currently being validated
     * @param array $params the additional name-value pairs given in the rule
     */
    public function validatePassword($attribute, $params)
    {
        if (!$this->hasErrors()) {
            $user = $this->getIdentity();

            if (!$user || $user->password !== $this->password) {
                $this->addError($attribute, 'Incorrect username or password.');
            }
        }
    }

    public function customFindIdentity()
    {
        $identity = new User();
        $identity->username = 'method';
        return $identity;
    }
}