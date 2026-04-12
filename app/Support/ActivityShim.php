<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

class ActivityShim
{
    protected ?string $logName = null;
    protected ?string $event = null;
    protected array $properties = [];
    protected $subject = null;
    protected $causer = null;

    public function __construct(?string $logName = null)
    {
        $this->logName = $logName;
    }

    public function causedBy($user): self
    {
        $this->causer = $user;
        return $this;
    }

    public function performedOn($model): self
    {
        $this->subject = $model;
        return $this;
    }

    public function event(?string $event): self
    {
        $this->event = $event;
        return $this;
    }

    public function withProperties($props): self
    {
        $this->properties = is_array($props) ? $props : (array) $props;
        return $this;
    }

    public function log(string $description): void
    {
        DB::table('activity_log')->insert([
            'log_name'    => $this->logName,
            'description' => $description,
            'subject_type'=> $this->subject ? get_class($this->subject) : null,
            'subject_id'  => $this->subject ? $this->subject->getKey() : null,
            'causer_type' => $this->causer ? get_class($this->causer) : null,
            'causer_id'   => $this->causer ? $this->causer->getKey() : null,
            'event'       => $this->event,
            'properties'  => json_encode($this->properties, JSON_UNESCAPED_UNICODE),
            'batch_uuid'  => null,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);
    }
}
