<?php

declare(strict_types=1);

namespace App\Shared\Repositories;

use App\Shared\Contracts\RepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Base repository encapsulating standard Eloquent data access operations.
 *
 * @template TModel of Model
 *
 * @implements RepositoryInterface<TModel>
 */
abstract class BaseRepository implements RepositoryInterface
{
    /**
     * Resolve the underlying Eloquent model class name.
     *
     * @return class-string<TModel>
     */
    abstract protected function model(): string;

    /**
     * @return Builder<TModel>
     */
    protected function query(): Builder
    {
        /** @var Builder<TModel> $query */
        $query = ($this->model())::query();

        return $query;
    }

    public function all(array $columns = ['*']): Collection
    {
        return $this->query()->get($columns);
    }

    public function paginate(int $perPage = 15, array $columns = ['*']): LengthAwarePaginator
    {
        return $this->query()->paginate($perPage, $columns);
    }

    public function findById(int|string $id, array $columns = ['*']): ?Model
    {
        return $this->query()->find($id, $columns);
    }

    public function findOrFail(int|string $id, array $columns = ['*']): Model
    {
        return $this->query()->findOrFail($id, $columns);
    }

    public function create(array $attributes): Model
    {
        return $this->query()->create($attributes);
    }

    public function update(Model $model, array $attributes): Model
    {
        $model->fill($attributes);
        $model->save();

        return $model->refresh();
    }

    public function delete(Model $model): bool
    {
        return (bool) $model->delete();
    }
}

