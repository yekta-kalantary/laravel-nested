<?php

namespace YektaKalantary\LaravelNested\Traits;

use Illuminate\Database\Schema\Blueprint;

trait Nested
{
    abstract public function nestedLeftColumn(): string;

    abstract public function nestedRightColumn(): string;

    abstract public function nestedParentColumn(): string;

    public function initializeNested()
    {
        $fillable = $this->fillable;
        $fillable[] = $this->nestedParentColumn();
        $this->fillable[] = array_unique($fillable);
    }

    public static function bootNested(): void
    {
        self::saving(function (self $model) {
            $model->setAttribute($model->nestedLeftColumn(), 0);
            $model->setAttribute($model->nestedRightColumn(), 0);
        });
        self::saved(function (self $model) {
            if ($model->getAttributeValue($model->nestedParentColumn())) {
                $model->saveAsNode();
            } else {
                $model->saveAsRoot();
            }
        });
    }

    public static function nestedColumns(Blueprint $table): void
    {
        $self = new self;
        $table->unsignedBigInteger($self->nestedLeftColumn())->default(0)->index('_lft');
        $table->unsignedBigInteger($self->nestedRightColumn())->default(0)->index('_rgt');
        $table->foreignId($self->nestedParentColumn())->nullable()
            ->constrained($self->getTable(), $self->getKeyName(), '_pid');
    }

    public static function nestedDropColumns(Blueprint $table): void
    {
        $self = new self;
        $table->dropIndex('_lft');
        $table->dropColumn($self->nestedLeftColumn());
        $table->dropIndex('_rgt');
        $table->dropColumn($self->nestedRightColumn());
        $table->dropForeign('_pid');
        $table->dropColumn($self->nestedParentColumn());
    }

    private function saveAsNode(): void
    {
        $max = $this->newQuery()
            ->where($this->getKeyName(), $this->getAttributeValue($this->nestedParentColumn()))
            ->valueOrFail($this->nestedRightColumn());

        $this->newQuery()
            ->where($this->nestedLeftColumn(), '>=', $max)
            ->increment($this->nestedLeftColumn(), 2);

        $this->newQuery()
            ->where($this->nestedRightColumn(), '>=', $max)
            ->increment($this->nestedRightColumn(), 2);

        $this->setAttribute($this->nestedLeftColumn(), $max);
        $this->setAttribute($this->nestedRightColumn(), $max + 1);
        $this->saveQuietly();
    }

    private function saveAsRoot(): void
    {
        $max = $this->newQuery()->max($this->nestedRightColumn());
        $this->setAttribute($this->nestedLeftColumn(), $max + 1);
        $this->setAttribute($this->nestedRightColumn(), $max + 2);
        $this->saveQuietly();
    }
}
