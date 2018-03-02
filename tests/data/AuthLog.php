<?php

namespace yii2tech\tests\unit\authlog\data;

use yii\db\ActiveRecord;

/**
 * @property int $id
 * @property int $userId
 * @property int $date
 * @property int $error
 * @property string $ip
 * @property string $host
 * @property string $url
 */
class AuthLog extends ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'AuthLog';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            ['userId', 'required'],
            ['date', 'required'],
        ];
    }
}