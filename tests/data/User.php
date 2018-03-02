<?php

namespace yii2tech\tests\unit\authlog\data;

use yii\base\NotSupportedException;
use yii\db\ActiveRecord;
use yii\web\IdentityInterface;
use yii2tech\authlog\AuthLogIdentityBehavior;

/**
 * @property int $id
 * @property string $username
 * @property string $password
 * @property string $authKey
 *
 * @property AuthLog[] $authLogs
 */
class User extends ActiveRecord implements IdentityInterface
{
    /**
     * {@inheritdoc}
     */
    public function behaviors()
    {
        return [
            'auth-log' => [
                '__class' => AuthLogIdentityBehavior::class,
            ],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'User';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            ['username', 'required'],
            ['password', 'required'],
        ];
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getAuthLogs()
    {
        return $this->hasMany(AuthLog::class, ['userId' => 'id']);
    }

    /**
     * {@inheritdoc}
     */
    public static function findIdentity($id)
    {
        return static::findOne($id);
    }

    /**
     * {@inheritdoc}
     */
    public static function findIdentityByAccessToken($token, $type = null)
    {
        throw new NotSupportedException('"findIdentityByAccessToken" is not implemented.');
    }

    /**
     * {@inheritdoc}
     */
    public function getId()
    {
        return $this->getPrimaryKey();
    }

    /**
     * {@inheritdoc}
     */
    public function getAuthKey()
    {
        return $this->authKey;
    }

    /**
     * {@inheritdoc}
     */
    public function validateAuthKey($authKey)
    {
        return $this->getAuthKey() === $authKey;
    }

    /**
     * Finds user by login name (username or email)
     *
     * @param string $loginName
     * @return static|null
     */
    public static function findByLoginName($loginName)
    {
        return static::find()->where(['username' => $loginName])->one();
    }

    /**
     * Emulates record deactivation for the test purposes
     */
    public function deactivate()
    {
        return $this->updateAttributes(['authKey' => 'inactive']);
    }
}