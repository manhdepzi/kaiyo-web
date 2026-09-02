<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Modules\CRM\Application\Actions\ManageOwnWishlist;
use App\Modules\Identity\Infrastructure\Persistence\Models\UserAccount;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class AccountWishlistController extends Controller
{
    public function store(Request $request, string $product, ManageOwnWishlist $wishlist): RedirectResponse
    {
        $account = $request->user();
        abort_unless($account instanceof UserAccount, 404);

        try {
            $wishlist->add($account, $product);
        } catch (DomainException $exception) {
            return back()->withErrors(['wishlist' => 'Chưa thể lưu sản phẩm: '.$exception->getMessage()]);
        }

        return back()->with('status', 'Sản phẩm đã được lưu vào yêu thích.');
    }

    public function destroy(Request $request, string $product, ManageOwnWishlist $wishlist): RedirectResponse
    {
        $account = $request->user();
        abort_unless($account instanceof UserAccount, 404);

        try {
            $wishlist->remove($account, $product);
        } catch (DomainException $exception) {
            return back()->withErrors(['wishlist' => 'Chưa thể bỏ lưu sản phẩm: '.$exception->getMessage()]);
        }

        return back()->with('status', 'Sản phẩm đã được bỏ khỏi danh sách yêu thích.');
    }
}
