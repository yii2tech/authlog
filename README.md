Identity Authentication Tracking extension for Yii2
===================================================

This extension provides identity authentication logging and tracking mechanism, which can be used
for 'brute-force' attack protection.

For license information check the [LICENSE](LICENSE.md)-file.

[![Latest Stable Version](https://poser.pugx.org/yii2tech/authlog/v/stable.png)](https://packagist.org/packages/yii2tech/authlog)
[![Total Downloads](https://poser.pugx.org/yii2tech/authlog/downloads.png)](https://packagist.org/packages/yii2tech/authlog)
[![Build Status](https://travis-ci.org/yii2tech/authlog.svg?branch=master)](https://travis-ci.org/yii2tech/authlog)


Installation
------------

The preferred way to install this extension is through [composer](http://getcomposer.org/download/).

Either run

```
php composer.phar require --prefer-dist yii2tech/authlog
```

or add

```json
"yii2tech/authlog": "*"
```

to the require section of your composer.json.


Usage
-----

This extension provides identity authentication logging and tracking mechanism, which can be used
for 'brute-force' attack protection.

Extension works through the ActiveRecord entity for the authentication attempt log.
The database migration for such entity creation can be following:

```php
$this->createTable('UserAuthLog', [
    'id' => $this->primaryKey(),
    'userId' => $this->integer(),
    'date' => $this->integer(),
    'cookieBased' => $this->boolean(),
    'duration' => $this->integer(),
    'error' => $this->string(),
    'ip' => $this->string(),
    'host' => $this->string(),
    'url' => $this->string(),
    'userAgent' => $this->string(),
]);
```

ActiveRecord model, which implements [[\yii\web\IdentityInterface]] should declare a 'has many' relation to this entity.
The logging mechanism is provided via [[\yii2tech\authlog\AuthLogIdentityBehavior]] behavior, which should be as well
attached to the identity class. For example:

```php
use yii\db\ActiveRecord;
use yii\web\IdentityInterface;
use yii2tech\authlog\AuthLogIdentityBehavior;

class User extends ActiveRecord implements IdentityInterface
{
    public function behaviors()
    {
        return [
            'authLog' => [
                'class' => AuthLogIdentityBehavior::className(),
                'authLogRelation' => 'authLogs',
                'defaultAuthLogData' => function ($model) {
                    return [
                        'ip' => $_SERVER['REMOTE_ADDR'],
                        'host' => @gethostbyaddr($_SERVER['REMOTE_ADDR']),
                        'url' => $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'],
                        'userAgent' => isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : null,
                    ];
                },
            ],
        ];
    }

    public function getAuthLogs()
    {
        return $this->hasMany(UserAuthLog::className(), ['userId' => 'id']);
    }

    // ...
}
```

> Note: because [[\yii2tech\authlog\AuthLogIdentityBehavior]] works through ActiveRecord the auth log storage can be
  any one, which have ActiveRecord layer implemented, such as Redis, MongoDB etc.

Being attached [[\yii2tech\authlog\AuthLogIdentityBehavior]] provides basic auth logging and statistic methods:

 - `logAuth()` writes auth log entry
 - `logAuthError()` writes auth log error entry
 - `getLastSuccessfulAuthLog()` returns last successful auth log entry
 - `getPreLastSuccessfulAuthLog()` returns pre-last successful auth log entry
 - `getLastLoginDate()` returns last successful login date
 - `getPreLastLoginDate()` returns pre-last successful login date
 - `hasFailedLoginSequence()` checks if there is sequence of failed login attempts of request length starting from now

Refer to [[\yii2tech\authlog\AuthLogIdentityBehavior]] for details about configuration and available methods.

Keep in mind that [[\yii2tech\authlog\AuthLogIdentityBehavior]] does NOT log authentication attempts automatically.
You'll have to invoke logging methods manually in a proper place to do so. However this extension provides other
tools, which cover this task.


## Automatic authentication logging <span id="automatic-authentication-logging"></span>

Although [[\yii2tech\authlog\AuthLogIdentityBehavior]] provides the basis for the auth logging, it does not
log anything automatically. Automatic logging of the successful authentication attempts are provided
via [[\yii2tech\authlog\AuthLogWebUserBehavior]] behavior.
[[\yii2tech\authlog\AuthLogWebUserBehavior]] should be attached to the 'user' application component (instance
of [[\yii\web\User]]). This could be done at the application configuration:

```php
return [
    'components' => [
        'user' => [
            'identityClass' => 'app\models\User',
            'loginUrl' => ['site/login'],
            'as authLog' => [
                'class' => 'yii2tech\authlog\AuthLogWebUserBehavior'
            ],
        ],
        // ...
    ],
    // ...
];
```

[[\yii2tech\authlog\AuthLogWebUserBehavior]] relies identity class has a [[\yii2tech\authlog\AuthLogIdentityBehavior]] attached
and writes auth log on any successful login made through owner [[\yii\web\User]] component, including the ones
based on cookie. However, this behavior can not log any failed authentication attempt, which should be done
elsewhere like login form.


## Logging authentication failures <span id="logging-authentication-failures"></span>

Logging authentication failures is specific to the authentication method used by application. Thus you are
responsible of its performing by yourself.

Most common authentication method is usage of username/password pair, which is asked via login web form.
In such workflow authentication failure should be written on invalid password entered.
This extension provides [[\yii2tech\authlog\AuthLogLoginFormBehavior]] behavior, which can be attached to the
login form model, providing authentication failures logging feature. For example:

```php
use app\models\User;
use yii2tech\authlog\AuthLogLoginFormBehavior;

class LoginForm extends Model
{
    public $username;
    public $password;
    public $rememberMe = true;

    public function behaviors()
    {
        return [
            'authLog' => [
                'class' => AuthLogLoginFormBehavior::className(),
                'findIdentity' => 'findIdentity',
            ],
        ];
    }

    public function findIdentity()
    {
        return User::findByUsername($this->username);
    }

    // ...
}
```

[[\yii2tech\authlog\AuthLogLoginFormBehavior]] automatically logs failure authentication attempt on owner
validation in case identity is found and there is any error on [[\yii2tech\authlog\AuthLogLoginFormBehavior::verifyIdentityAttributes]].


## "Brute force" protection <span id="brute-force-protection"></span>

In addition to simple logging [[\yii2tech\authlog\AuthLogLoginFormBehavior]] provide built-in "brute force" attack
protection mechanism, which have 2 levels:

 - require robot verification (CAPTCHA) after [[\yii2tech\authlog\AuthLogLoginFormBehavior::verifyRobotFailedLoginSequence]] sequence login failures
 - deactivation of the identity record after [[\yii2tech\authlog\AuthLogLoginFormBehavior::deactivateFailedLoginSequence]] sequence login failures

For example:

```php
use app\models\User;
use yii2tech\authlog\AuthLogLoginFormBehavior;

class LoginForm extends Model
{
    public $username;
    public $password;
    public $rememberMe = true;
    public $verifyCode;

    public function behaviors()
    {
        return [
            'authLog' => [
                'class' => AuthLogLoginFormBehavior::className(),
                'findIdentity' => 'findIdentity',
                'verifyRobotAttribute' => 'verifyCode',
                'deactivateIdentity' => function ($identity) {
                    return $this->updateAttributes(['statusId' => User::STATUS_SUSPENDED]);;
                },
            ],
        ];
    }

    public function rules()
    {
        return [
            [['username', 'password'], 'required'],
            ['rememberMe', 'boolean'],
            ['password', 'validatePassword'],
            ['verifyCode', 'safe'],
        ];
    }

    public function findIdentity()
    {
        return User::findByUsername($this->username);
    }

    // ...
}
```

Robot verification requires extra processing at the view layer, which should render CAPTCHA only if it is necessary:

```php
<?php $form = ActiveForm::begin(['id' => 'login-form']); ?>

<?= $form->field($model, 'username') ?>
<?= $form->field($model, 'password')->passwordInput() ?>

<?php if (Yii::$app->user->enableAutoLogin) : ?>
    <?= $form->field($model, 'rememberMe')->checkbox() ?>
<?php endif; ?>

<?php if ($model->isVerifyRobotRequired) : ?>
    <?= $form->field($model, 'verifyCode')->widget(Captcha::className(), [
        'template' => '{image}{input}',
    ]) ?>
<?php endif; ?>

<div class="form-group">
    <?= Html::submitButton(Yii::t('admin', 'Do Login'), ['class' => 'btn btn-primary', 'name' => 'login-button']) ?>
</div>

<?php ActiveForm::end(); ?>
```

**Heads up!** Although [[\yii2tech\authlog\AuthLogLoginFormBehavior]] is supposed to cover most common web login
form workflow, do not limit yourself with it. Be ready to create your own implementation of feature.


## Garbage Collection <span id="garbage-collection"></span>

Logging every authentication attempt for every user in the system may cause log storage (database) consuming
too much space without much purpose. Usually there is no need to store all auth attempts for the single user
starting from his registration. Thus a built-in garbage collection mechanism provided.

Using [[\yii2tech\authlog\AuthLogIdentityBehavior]] triggers garbage collection automatically on log writing.
You may setup `gcProbability` and `gcLimit` to control the process or invoke `gcAuthLogs()` directly.
