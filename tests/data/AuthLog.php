<?php

namespace yii2tech\tests\unit\authlog\data;

use yii\db\ActiveRecord;

/**
 * @property integer $id
 * @property integer $userId
 * @property integer $date
 * @property integer $error
 * @property string $ip
 * @property string $host
 * @property string $url
 */
class AuthLog extends ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'AuthLog';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            ['userId', 'required'],
            ['date', 'required'],
        ];
    }
}