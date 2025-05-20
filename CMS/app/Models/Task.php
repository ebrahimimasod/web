<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Task extends Model
{
    const TABLE = 'tasks';

    protected $table = self::TABLE;


    const COL_ID = 'id';
    const COL_NAME = 'name';
    const COL_TYPE = 'type';
    const COL_TRACK_ID = 'track_id';
    const COL_PAYLOAD = 'payload';
    const COL_STATUS = 'status';
    const COL_STEP = 'step';
    const COL_RESULT = 'result';
    const COL_ERRORS = 'errors';
    const COL_FINISHED_AT = 'finished_at';
    const COL_HEARTBEAT_AT = 'heartbeat_at';
    const COL_TIMEOUT_AT = 'timeout_at';

    protected $casts = [
        self::COL_PAYLOAD => 'json'
    ];


    const STATUS_PROCESSING = 'processing';
    const STATUS_FINISHED = 'finished';
    const STATUS_FAILED = 'failed';



    const TYPE_BACKUP = 'backup';
    const TYPE_RESTORE = 'restore';
    const TYPE_UPDATE = 'update';
    const TYPES = [
        self::TYPE_BACKUP,
        self::TYPE_RESTORE,
        self::TYPE_UPDATE,
    ];


    protected $fillable = [
        self::COL_NAME,
        self::COL_TYPE,
        self::COL_TRACK_ID,
        self::COL_PAYLOAD,
        self::COL_STATUS,
        self::COL_STEP,
        self::COL_RESULT,
        self::COL_ERRORS,
        self::COL_FINISHED_AT,
        self::COL_HEARTBEAT_AT,
        self::COL_TIMEOUT_AT,
    ];

}
