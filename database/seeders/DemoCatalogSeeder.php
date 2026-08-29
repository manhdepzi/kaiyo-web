<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Catalog\Infrastructure\Persistence\Models\Brand;
use App\Modules\Catalog\Infrastructure\Persistence\Models\Category;
use App\Modules\Catalog\Infrastructure\Persistence\Models\Product;
use App\Modules\Catalog\Infrastructure\Persistence\Models\Variant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

final class DemoCatalogSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $brand = Brand::query()->updateOrCreate(
                ['slug' => 'kaiyo'],
                ['name' => 'Kaiyo', 'status' => 'active'],
            );
            $ducts = Category::query()->updateOrCreate(
                ['slug' => 'ong-gio-phu-kien'],
                ['name' => 'Ống gió & Phụ kiện', 'status' => 'active', 'sort_order' => 10],
            );
            $air = Category::query()->updateOrCreate(
                ['slug' => 'mieng-gio-van-gio'],
                ['name' => 'Miệng gió & Van gió', 'status' => 'active', 'sort_order' => 20],
            );

            $this->product($brand, $ducts, [
                'name' => 'Ống gió tròn inox',
                'slug' => 'ong-gio-tron-inox',
                'description' => 'Ống gió tròn inox dùng cho hệ thống thông gió. Chọn đường kính phù hợp để gửi yêu cầu mua hàng hoặc báo giá.',
                'variants' => [['KY-OGI-100', 'Đường kính Ø100'], ['KY-OGI-150', 'Đường kính Ø150'], ['KY-OGI-200', 'Đường kính Ø200']],
            ]);
            $this->product($brand, $ducts, [
                'name' => 'Ống gió bọc tấm MgO tiêu chuẩn EI',
                'slug' => 'ong-gio-boc-tam-mgo-tieu-chuan-ei',
                'description' => 'Giải pháp ống gió bọc tấm MgO cho hệ thống yêu cầu giới hạn chịu lửa. Cấp EI, kích thước và cấu tạo được xác nhận theo hồ sơ kỹ thuật của từng dự án.',
                'variants' => [['KY-MGO-EI30', 'Cấu hình EI30'], ['KY-MGO-EI60', 'Cấu hình EI60'], ['KY-MGO-EI90', 'Cấu hình EI90']],
            ]);
            $this->product($brand, $ducts, [
                'name' => 'Ống gió tròn tôn mạ kẽm',
                'slug' => 'ong-gio-tron-ton-ma-kem',
                'description' => 'Ống gió tròn bằng tôn mạ kẽm dùng cho hệ thống cấp, hồi và thải gió. Đường kính, chiều dày và kiểu kết nối được chốt khi duyệt bản vẽ.',
                'variants' => [['KY-OGM-100', 'Đường kính Ø100'], ['KY-OGM-150', 'Đường kính Ø150'], ['KY-OGM-200', 'Đường kính Ø200']],
            ]);
            $this->product($brand, $ducts, [
                'name' => 'Ống gió vuông bích TDC',
                'slug' => 'ong-gio-vuong-bich-tdc',
                'description' => 'Ống gió vuông sử dụng hệ bích TDC, sản xuất theo kích thước và yêu cầu kỹ thuật của công trình. Báo giá được lập theo cấu hình đã xác nhận.',
                'variants' => [['KY-TDC-300', 'Kích thước 300 × 300 mm'], ['KY-TDC-400', 'Kích thước 400 × 400 mm'], ['KY-TDC-CUSTOM', 'Kích thước theo bản vẽ']],
            ]);
            $this->product($brand, $air, [
                'name' => 'Miệng gió chắn mưa Nan Z',
                'slug' => 'mieng-gio-chan-mua-nan-z',
                'description' => 'Miệng gió cánh chắn mưa dạng Nan Z tháo lắp, dùng tại vị trí lấy gió hoặc thải gió ngoài trời.',
                'variants' => [['KY-MGZ-300', 'Kích thước 300 × 300 mm'], ['KY-MGZ-400', 'Kích thước 400 × 400 mm'], ['KY-MGZ-500', 'Kích thước 500 × 500 mm']],
            ]);
            $this->product($brand, $air, [
                'name' => 'Van cầu chì tròn',
                'slug' => 'van-cau-chi-tron',
                'description' => 'Van cầu chì dạng tròn cho hệ thống ống gió. Thông số kỹ thuật và cấu hình cuối cùng được xác nhận khi báo giá.',
                'variants' => [['KY-FDC-100', 'Đường kính Ø100'], ['KY-FDC-150', 'Đường kính Ø150'], ['KY-FDC-200', 'Đường kính Ø200']],
            ]);
            $this->product($brand, $air, [
                'name' => 'Van chặn lửa động cơ',
                'slug' => 'van-chan-lua-dong-co',
                'description' => 'Van chặn lửa vận hành bằng động cơ cho hệ thống thông gió. Điện áp điều khiển, kích thước và cấp chịu lửa được xác nhận theo thiết kế dự án.',
                'variants' => [['KY-MFD-300', 'Kích thước 300 × 300 mm'], ['KY-MFD-400', 'Kích thước 400 × 400 mm'], ['KY-MFD-CUSTOM', 'Kích thước theo bản vẽ']],
            ]);
            $this->product($brand, $air, [
                'name' => 'Van chặn lửa FD tiêu chuẩn EI90',
                'slug' => 'van-chan-lua-fd-tieu-chuan-ei-90',
                'description' => 'Van chặn lửa FD cho vị trí yêu cầu tiêu chuẩn EI90. Cấu tạo, kích thước lắp đặt và hồ sơ nghiệm thu được xác nhận trước khi sản xuất.',
                'variants' => [['KY-FD90-300', 'Kích thước 300 × 300 mm'], ['KY-FD90-400', 'Kích thước 400 × 400 mm'], ['KY-FD90-CUSTOM', 'Kích thước theo bản vẽ']],
            ]);
            $this->product($brand, $air, [
                'name' => 'Van chặn lửa gắn cầu chì KY.FD-D/E-120',
                'slug' => 'van-chan-lua-gan-cau-chi-ky-fd-d-e-120',
                'description' => 'Van chặn lửa gắn cầu chì cho cấu hình yêu cầu giới hạn chịu lửa EI120. Thông số cuối cùng tuân theo bản vẽ và hồ sơ kỹ thuật được duyệt.',
                'variants' => [['KY-FD120-300', 'Kích thước 300 × 300 mm'], ['KY-FD120-400', 'Kích thước 400 × 400 mm'], ['KY-FD120-CUSTOM', 'Kích thước theo bản vẽ']],
            ]);
        }, 3);
    }

    /**
     * @param  array{name: string, slug: string, description: string, variants: list<array{string, string}>}  $data
     */
    private function product(Brand $brand, Category $category, array $data): void
    {
        $product = Product::query()->updateOrCreate(
            ['slug' => $data['slug']],
            [
                'brand_id' => $brand->getKey(),
                'primary_category_id' => $category->getKey(),
                'name' => $data['name'],
                'description' => $data['description'],
                'status' => 'active',
            ],
        );

        foreach ($data['variants'] as [$sku, $name]) {
            Variant::query()->updateOrCreate(
                ['sku' => $sku],
                ['product_id' => $product->getKey(), 'name' => $name, 'quantity_scale' => 0, 'status' => 'active'],
            );
        }
    }
}
