<?php

namespace App\Modules\Inventory\Application;

use App\Models\Product;
use App\Models\ProductImage;
use App\Support\TenantPrivateMedia;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Throwable;

class ManageProductImages
{
    public function __construct(
        private readonly TenantPrivateMedia $media,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(
        Product $product,
        UploadedFile $file,
        array $attributes,
    ): ProductImage {
        $path = $this->media->store($file, "products/{$product->getKey()}");

        try {
            return DB::transaction(function () use ($product, $path, $attributes): ProductImage {
                $isPrimary = (bool) ($attributes['is_primary'] ?? ! $product->images()->exists());

                if ($isPrimary) {
                    $product->images()->update(['is_primary' => false]);
                }

                return $product->images()->create([
                    'image_path' => $path,
                    'alt_text' => $attributes['alt_text'] ?? $product->name,
                    'is_primary' => $isPrimary,
                    'sort_order' => $attributes['sort_order']
                        ?? ((int) $product->images()->max('sort_order') + 1),
                ]);
            });
        } catch (Throwable $exception) {
            $this->media->delete($path);

            throw $exception;
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(
        Product $product,
        ProductImage $image,
        array $attributes,
    ): ProductImage {
        $this->ensureOwnedBy($product, $image);

        DB::transaction(function () use ($product, $image, $attributes): void {
            if (($attributes['is_primary'] ?? false) === true) {
                $product->images()
                    ->whereKeyNot($image->getKey())
                    ->update(['is_primary' => false]);
            }

            $image->update($attributes);
        });

        return $image->refresh();
    }

    public function delete(Product $product, ProductImage $image): void
    {
        $this->ensureOwnedBy($product, $image);

        $path = $image->image_path;
        $wasPrimary = $image->is_primary;

        DB::transaction(function () use ($product, $image, $wasPrimary): void {
            $image->delete();

            if ($wasPrimary) {
                $product->images()
                    ->orderBy('sort_order')
                    ->orderBy('id')
                    ->first()
                    ?->update(['is_primary' => true]);
            }
        });

        $this->media->delete($path);
    }

    private function ensureOwnedBy(Product $product, ProductImage $image): void
    {
        abort_unless(
            (int) $image->product_id === (int) $product->getKey(),
            404,
        );
    }
}
