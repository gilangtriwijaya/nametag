<?php

namespace App\Observers;

use App\Support\ChangeDiff;
use Illuminate\Database\Eloquent\Model;

class LogChangesObserver
{
    protected array $except = [
        'password','remember_token','updated_at','created_at','deleted_at',
    ];

    public function created(Model $model): void
    {
        activity('model')
            ->performedOn($model)
            ->causedBy(auth()->user())
            ->event('created')
            ->withProperties([
                'attributes' => $model->getAttributes(),
            ])
            ->log(class_basename($model).' dibuat');
    }

    public function updated(Model $model): void
    {
        $changes = ChangeDiff::diff($model->getOriginal(), $model->getAttributes(), $this->except);

        // hanya log jika ada perubahan berarti
        if (empty($changes)) return;

        activity('model')
            ->performedOn($model)
            ->causedBy(auth()->user())
            ->event('updated')
            ->withProperties(['changes' => $changes])
            ->log(class_basename($model).' diubah');
    }

    public function deleted(Model $model): void
    {
        activity('model')
            ->performedOn($model)
            ->causedBy(auth()->user())
            ->event('deleted')
            ->withProperties([
                'attributes' => $model->getAttributes(),
            ])
            ->log(class_basename($model).' dihapus');
    }
}
