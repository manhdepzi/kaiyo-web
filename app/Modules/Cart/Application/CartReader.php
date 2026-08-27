<?php

declare(strict_types=1);

namespace App\Modules\Cart\Application;

use App\Modules\Cart\Application\Data\CartView;
use App\Modules\Cart\Application\Data\CartViewLine;
use App\Modules\Cart\Infrastructure\Persistence\Models\Cart;
use Illuminate\Support\Facades\DB;

final class CartReader
{
    public function read(Cart $cart): CartView
    {
        $rows = DB::table('cart_lines as cl')
            ->join('variants as v', 'v.id', '=', 'cl.variant_id')
            ->join('products as p', 'p.id', '=', 'v.product_id')
            ->where('cl.cart_id', $cart->getKey())
            ->orderBy('cl.id')
            ->get([
                'cl.id', 'cl.quantity', 'cl.advisory_unit_amount', 'cl.advisory_line_amount',
                'cl.advisory_available_qty', 'cl.advisory_status', 'v.public_id as variant_public_id',
                'v.name as variant_name', 'v.sku', 'p.name as product_name', 'p.slug as product_slug',
            ]);

        return new CartView($cart->public_id, $cart->lock_version, array_values($rows->map(fn (object $row) => new CartViewLine(
            (int) $row->id,
            (string) $row->variant_public_id,
            (string) $row->product_name,
            (string) $row->product_slug,
            (string) $row->variant_name,
            (string) $row->sku,
            (string) $row->quantity,
            $row->advisory_unit_amount === null ? null : (int) $row->advisory_unit_amount,
            $row->advisory_line_amount === null ? null : (int) $row->advisory_line_amount,
            $row->advisory_available_qty === null ? null : (string) $row->advisory_available_qty,
            (string) $row->advisory_status,
        ))->all()));
    }
}
