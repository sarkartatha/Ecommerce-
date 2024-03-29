<?php

use Carbon\Carbon;
use Webkul\Checkout\Models\Cart;
use Webkul\Checkout\Models\CartItem;
use Webkul\Customer\Models\Customer;
use Webkul\Customer\Models\Wishlist;
use Webkul\Faker\Helpers\Product as ProductFaker;
use Webkul\Marketing\Models\SearchTerm;
use Webkul\Product\Models\Product;
use Webkul\Product\Models\ProductReview;
use Webkul\Sales\Models\Invoice;
use Webkul\Sales\Models\Order;
use Webkul\Sales\Models\OrderAddress;
use Webkul\Sales\Models\OrderItem;
use Webkul\Sales\Models\OrderPayment;

use function Pest\Laravel\get;

it('should returns the reporting product page', function () {
    // Act and Assert
    $this->loginAsAdmin();

    get(route('admin.reporting.products.index'))
        ->assertOk()
        ->assertSeeText(trans('admin::app.reporting.products.index.title'))
        ->assertSeeText(trans('admin::app.reporting.products.index.last-search-terms'))
        ->assertSeeText(trans('admin::app.reporting.products.index.products-with-most-reviews'))
        ->assertSeeText(trans('admin::app.reporting.products.index.products-with-most-visits'))
        ->assertSeeText(trans('admin::app.reporting.products.index.view-details'))
        ->assertSeeText(trans('admin::app.reporting.products.index.top-selling-products-by-quantity'))
        ->assertSeeText(trans('admin::app.reporting.products.index.top-selling-products-by-revenue'))
        ->assertSeeText(trans('admin::app.reporting.products.index.view-details'))
        ->assertSeeText(trans('admin::app.reporting.products.index.top-search-terms'));
});

it('should return the product reporting stats', function () {
    // Arrange
    $product = (new ProductFaker([
        'attributes' => [
            5 => 'new',
        ],

        'attribute_value' => [
            'new' => [
                'boolean_value' => true,
            ],
        ],
    ]))
        ->getSimpleProductFactory()
        ->create();

    $customer = Customer::factory()->create();

    CartItem::factory()->create([
        'product_id' => $product->id,
        'sku'        => $product->sku,
        'type'       => $product->type,
        'name'       => $product->name,
        'cart_id'    => $cartId = Cart::factory()->create([
            'customer_id'         => $customer->id,
            'customer_email'      => $customer->email,
            'customer_first_name' => $customer->first_name,
            'customer_last_name'  => $customer->last_name,
        ])->id,
    ]);

    $order = Order::factory()->create([
        'cart_id'             => $cartId,
        'customer_id'         => $customer->id,
        'customer_email'      => $customer->email,
        'customer_first_name' => $customer->first_name,
        'customer_last_name'  => $customer->last_name,
    ]);

    OrderItem::factory()->create([
        'product_id'   => $product->id,
        'order_id'     => $order->id,
        'qty_invoiced' => 1,
    ]);

    OrderPayment::factory()->create([
        'order_id' => $order->id,
    ]);

    OrderAddress::factory()->create([
        'order_id'     => $order->id,
        'cart_id'      => $cartId,
        'address_type' => OrderAddress::ADDRESS_TYPE_BILLING,
    ]);

    Invoice::factory([
        'order_id' => $order->id,
        'state'    => 'paid',
    ])->create();

    // Act and Assert
    $this->loginAsAdmin();

    get(route('admin.reporting.products.stats', [
        'type' => 'total-sold-quantities',
    ]))
        ->assertOk()
        ->assertJsonPath('statistics.quantities.current', 1)
        ->assertJsonPath('statistics.over_time.current.30.total', 1);
});

it('should return the total products added to wishlist reporting stats', function () {
    // Arrange
    $product = (new ProductFaker([
        'attributes' => [
            5 => 'new',
        ],

        'attribute_value' => [
            'new' => [
                'boolean_value' => true,
            ],
        ],
    ]))
        ->getSimpleProductFactory()
        ->create();

    $customer = Customer::factory()->create();

    Wishlist::factory()->create([
        'channel_id'  => core()->getDefaultChannel()->id,
        'product_id'  => $product->id,
        'customer_id' => $customer->id,
    ]);

    // Act and Assert
    $this->loginAsAdmin();

    get(route('admin.reporting.products.stats', [
        'type' => 'total-products-added-to-wishlist',
    ]))
        ->assertOk()
        ->assertJsonPath('statistics.wishlist.current', 1)
        ->assertJsonPath('statistics.over_time.current.30.total', 1);
});

it('should return the top selling products by revenue reporting stats', function () {
    // Arrange
    $product = (new ProductFaker([
        'attributes' => [
            5 => 'new',
        ],

        'attribute_value' => [
            'new' => [
                'boolean_value' => true,
            ],
        ],
    ]))
        ->getSimpleProductFactory()
        ->create();

    $customer = Customer::factory()->create();

    CartItem::factory()->create([
        'product_id' => $product->id,
        'sku'        => $product->sku,
        'type'       => $product->type,
        'name'       => $product->name,
        'cart_id'    => $cartId = Cart::factory()->create([
            'customer_id'         => $customer->id,
            'customer_email'      => $customer->email,
            'customer_first_name' => $customer->first_name,
            'customer_last_name'  => $customer->last_name,
        ])->id,
    ]);

    $order = Order::factory()->create([
        'cart_id'             => $cartId,
        'customer_id'         => $customer->id,
        'customer_email'      => $customer->email,
        'customer_first_name' => $customer->first_name,
        'customer_last_name'  => $customer->last_name,
    ]);

    $orderItem = OrderItem::factory()->create([
        'product_id'          => $product->id,
        'order_id'            => $order->id,
        'base_total_invoiced' => $order->base_sub_total_invoiced,
    ]);

    OrderPayment::factory()->create([
        'order_id' => $order->id,
    ]);

    OrderAddress::factory()->create([
        'order_id'     => $order->id,
        'cart_id'      => $cartId,
        'address_type' => OrderAddress::ADDRESS_TYPE_BILLING,
    ]);

    Invoice::factory()->create([
        'order_id'      => $order->id,
        'state'         => 'paid',
    ]);

    // Act and Assert
    $this->loginAsAdmin();

    get(route('admin.reporting.products.stats', [
        'type' => 'top-selling-products-by-revenue',
    ]))
        ->assertOk()
        ->assertJsonPath('statistics.0.id', $orderItem->product->id)
        ->assertJsonPath('statistics.0.price', $orderItem->product?->price)
        ->assertJsonPath('statistics.0.formatted_price', core()->formatBasePrice($orderItem->price));
});

it('should return the top selling products by quantity reporting stats', function () {
    // Arrange
    $product = (new ProductFaker([
        'attributes' => [
            5 => 'new',
        ],

        'attribute_value' => [
            'new' => [
                'boolean_value' => true,
            ],
        ],
    ]))
        ->getSimpleProductFactory()
        ->create();

    $customer = Customer::factory()->create();

    CartItem::factory()->create([
        'product_id' => $product->id,
        'sku'        => $product->sku,
        'type'       => $product->type,
        'name'       => $product->name,
        'cart_id'    => $cartId = Cart::factory()->create([
            'customer_id'         => $customer->id,
            'customer_email'      => $customer->email,
            'customer_first_name' => $customer->first_name,
            'customer_last_name'  => $customer->last_name,
        ])->id,
    ]);

    $order = Order::factory()->create([
        'cart_id'             => $cartId,
        'customer_id'         => $customer->id,
        'customer_email'      => $customer->email,
        'customer_first_name' => $customer->first_name,
        'customer_last_name'  => $customer->last_name,
    ]);

    $orderItem = OrderItem::factory()->create([
        'product_id'   => $product->id,
        'order_id'     => $order->id,
        'qty_invoiced' => $order->base_sub_total_invoiced,
    ]);

    OrderPayment::factory()->create([
        'order_id' => $order->id,
    ]);

    OrderAddress::factory()->create([
        'order_id'     => $order->id,
        'cart_id'      => $cartId,
        'address_type' => OrderAddress::ADDRESS_TYPE_BILLING,
    ]);

    Invoice::factory()->create([
        'order_id' => $order->id,
        'state'    => 'paid',
    ]);

    // Act and Assert
    $this->loginAsAdmin();

    get(route('admin.reporting.products.stats', [
        'type' => 'top-selling-products-by-quantity',
    ]))
        ->assertOk()
        ->assertJsonPath('statistics.0.id', $orderItem->product->id)
        ->assertJsonPath('statistics.0.price', $orderItem->product?->price);
});

it('should return the products with most reviews reporting stats', function () {
    // Arrange
    $product = (new ProductFaker([
        'attributes' => [
            5 => 'new',
        ],

        'attribute_value' => [
            'new' => [
                'boolean_value' => true,
            ],
        ],
    ]))
        ->getSimpleProductFactory()
        ->create();

    $customer = Customer::factory()->create();

    ProductReview::factory()->count(2)->create([
        'status'      => 'approved',
        'comment'     => fake()->sentence(20),
        'product_id'  => $product->id,
        'customer_id' => $customer->id,
    ]);

    // Act and Assert
    $this->loginAsAdmin();

    get(route('admin.reporting.products.stats', [
        'type' => 'products-with-most-reviews',
    ]))
        ->assertOk()
        ->assertJsonPath('statistics.0.product_id', $product->id)
        ->assertJsonPath('statistics.0.reviews', 2)
        ->assertJsonPath('statistics.0.product.id', $product->id)
        ->assertJsonPath('statistics.0.product.type', $product->type);
});

it('should return the products with most visits reporting stats', function () {
    // Arrange
    $product = (new ProductFaker([
        'attributes' => [
            5 => 'new',
        ],

        'attribute_value' => [
            'new' => [
                'boolean_value' => true,
            ],
        ],
    ]))
        ->getSimpleProductFactory()
        ->create();

    // Act and Assert
    $this->loginAsAdmin();

    visitor()->visit($product);

    get(route('admin.reporting.products.stats', [
        'type' => 'products-with-most-visits',
    ]))
        ->assertOk()
        ->assertJsonPath('statistics.0.visitable_id', $product->id)
        ->assertJsonPath('statistics.0.visits', 1)
        ->assertJsonPath('statistics.0.visitable_type', Product::class)
        ->assertJsonPath('statistics.0.visitable.id', $product->id)
        ->assertJsonPath('statistics.0.visitable.type', $product->type);
});

it('should return the last search terms reporting stats', function () {
    // Arrange
    (new ProductFaker([
        'attributes' => [
            5 => 'new',
        ],

        'attribute_value' => [
            'new' => [
                'boolean_value' => true,
            ],
        ],
    ]))
        ->getSimpleProductFactory()
        ->create();

    $searchTerm = SearchTerm::factory()->create();

    // Act and Assert
    $this->loginAsAdmin();

    get(route('admin.reporting.products.stats', [
        'type' => 'last-search-terms',
    ]))
        ->assertOk()
        ->assertJsonPath('statistics.0.id', $searchTerm->id)
        ->assertJsonPath('statistics.0.term', $searchTerm->term)
        ->assertJsonPath('statistics.0.redirect_url', $searchTerm->redirect_url);
});

it('should return top search terms reporting stats', function () {
    // Arrange
    (new ProductFaker([
        'attributes' => [
            5 => 'new',
        ],

        'attribute_value' => [
            'new' => [
                'boolean_value' => true,
            ],
        ],
    ]))
        ->getSimpleProductFactory()
        ->create();

    // Act and Assert
    $this->loginAsAdmin();

    $searchTerm = SearchTerm::factory()->create();

    get(route('admin.reporting.products.stats', [
        'type' => 'top-search-terms',
    ]))
        ->assertOk()
        ->assertJsonPath('statistics.0.id', $searchTerm->id)
        ->assertJsonPath('statistics.0.term', $searchTerm->term)
        ->assertJsonPath('statistics.0.redirect_url', $searchTerm->redirect_url);
});

it('should return the downloadable response for product stats', function () {
    // Arrange
    $period = fake()->randomElement(['day', 'month', 'year']);

    $start = Carbon::now();

    if ($period === 'day') {
        $end = $start->copy()->addDay();
    } elseif ($period === 'month') {
        $end = $start->copy()->addMonth();
    } else {
        $end = $start->copy()->addYear();
    }

    // Act and Assert
    $this->loginAsAdmin();

    get(route('admin.reporting.products.export', [
        'start'  => $start->toDateString(),
        'end'    => $end->toDateString(),
        'format' => $format = fake()->randomElement(['csv', 'xls']),
        'period' => $period,
        'type'   => 'total-sold-quantities',
    ]))
        ->assertOk()
        ->assertDownload('total-sold-quantities.'.$format);
});

it('should return the product view page', function () {
    // Act and Assert
    $this->loginAsAdmin();

    get(route('admin.reporting.products.view', [
        'type' => 'total-sold-quantities',
    ]))
        ->assertOk()
        ->assertSeeText(trans('admin::app.reporting.products.index.total-sold-quantities'))
        ->assertSeeText(trans('admin::app.reporting.view.export-csv'))
        ->assertSeeText(trans('admin::app.reporting.view.export-xls'));
});

it('should returns the report the product', function () {
    // Arrange
    $product = (new ProductFaker([
        'attributes' => [
            5 => 'new',
        ],

        'attribute_value' => [
            'new' => [
                'boolean_value' => true,
            ],
        ],
    ]))
        ->getSimpleProductFactory()
        ->create();

    $customer = Customer::factory()->create();

    CartItem::factory()->create([
        'product_id' => $product->id,
        'sku'        => $product->sku,
        'type'       => $product->type,
        'name'       => $product->name,
        'cart_id'    => $cartId = Cart::factory()->create([
            'customer_id'         => $customer->id,
            'customer_email'      => $customer->email,
            'customer_first_name' => $customer->first_name,
            'customer_last_name'  => $customer->last_name,
        ])->id,
    ]);

    $order = Order::factory()->create([
        'cart_id'             => $cartId,
        'customer_id'         => $customer->id,
        'customer_email'      => $customer->email,
        'customer_first_name' => $customer->first_name,
        'customer_last_name'  => $customer->last_name,
    ]);

    OrderItem::factory()->create([
        'product_id'   => $product->id,
        'order_id'     => $order->id,
        'qty_invoiced' => 1,
    ]);

    OrderPayment::factory()->create([
        'order_id' => $order->id,
    ]);

    OrderAddress::factory()->create([
        'order_id'     => $order->id,
        'cart_id'      => $cartId,
        'address_type' => OrderAddress::ADDRESS_TYPE_BILLING,
    ]);

    Invoice::factory([
        'order_id' => $order->id,
        'state'    => 'paid',
    ])->create();

    // Act and Assert
    $this->loginAsAdmin();

    get(route('admin.reporting.products.view.stats', [
        'type'   => 'total-sold-quantities',
    ]))
        ->assertOk()
        ->assertJsonPath('statistics.columns.0.key', 'label')
        ->assertJsonPath('statistics.columns.1.key', 'total')
        ->assertJsonPath('statistics.records.30.total', 1);
});
