<?php

declare(strict_types=1);

namespace App\Shared\Contracts;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * @template TModel of Model
 */
interface RepositoryInterface
{
    /**
     * @return Collection<int, TModel>
     */
    public function all(array $columns = ['*']): Collection;

    /**
     * @return LengthAwarePaginator<TModel>
     */
    public function paginate(int $perPage = 15, array $columns = ['*']): LengthAwarePaginator;

    /**
     * @return TModel|null
     */
    public function findById(int|string $id, array $columns = ['*']): ?Model;

    /**
     * @return TModel
     */
    public function findOrFail(int|string $id, array $columns = ['*']): Model;

    /**
     * @param  array<string, mixed>  $attributes
     * @return TModel
     */
    public function create(array $attributes): Model;

    /**
     * @param  TModel  $model
     * @param  array<string, mixed>  $attributes
     * @return TModel
     */
    public function update(Model $model, array $attributes): Model;

    /**
     * Soft-deletes the given model instance.
     *
     * @param  TModel  $model
     */
    public function delete(Model $model): bool;
}

