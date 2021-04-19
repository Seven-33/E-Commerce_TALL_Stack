<?php

namespace App\Http\Livewire\Shop;

use App\Helpers\Cart as CartHelper;
use App\Helpers\Taxes as TaxesHelper;
use App\Models\PaymentMethod;
use Livewire\Component;
use PragmaRX\Countries\Package\Countries;
use App\Models\Order;
use App\Models\ShippingCarrier;
use App\Models\UserAddress;
use App\Models\UserInvoiceAddress;

class Checkout extends Component
{
    /**
     * Selected Shipping Address Id.
     *
     * @var int
     */
    public $addressId;

    /**
     * Shipping Address data.
     *
     * @var UserInvoiceAddress
     */
    public UserAddress $address;

    /**
     * Selected Invoice Address Id.
     *
     * @var int
     */
    public $invoiceAddressId;

    /**
     * Invoice Address data.
     *
     * @var UserInvoiceAddress
     */
    public UserInvoiceAddress $invoiceAddress;

    /**
     * Show or hide the invoice address form.
     *
     * @var boolean
     */
    public $showInvoiceForm = false;

    /**
     * Selected Shipping Carrier Id.
     *
     * @var int
     */
    public $shippingCarrierId;

    /**
     * Selected PAyment Method Id.
     *
     * @var int
     */
    public $paymentMethodId;

    /**
     * Total Order price.
     *
     * @var float
     */
    public $price;

    /**
     * Total Order Taxes price.
     *
     * @var float
     */
    public $taxes;

    /**
     * Form rules.
     *
     * @var array
     */
    protected $rules = [
        // Shipping
        'address.firstname' => 'required|string',
        'address.lastname' => 'required|string',
        'address.country' => 'required|string',
        'address.region' => 'required|string',
        'address.city' => 'required|string',
        'address.address' => 'required|string',
        'address.zip' => 'required',
        'address.phone' => 'required',
        // Invoice
        'invoiceAddress.vat' => 'required_unless:invoiceAddressId,-1',
        'invoiceAddress.name' => 'required_unless:invoiceAddressId,-1|string',
        'invoiceAddress.phone' => 'required_unless:invoiceAddressId,-1',
        'invoiceAddress.country' => 'required_unless:invoiceAddressId,-1|string',
        'invoiceAddress.address' => 'required_unless:invoiceAddressId,-1|string',
        'invoiceAddress.region' => 'required_unless:invoiceAddressId,-1|string',
        'invoiceAddress.city' => 'required_unless:invoiceAddressId,-1|string',
        'invoiceAddress.zip' => 'required_unless:invoiceAddressId,-1',
        // Others
        'shippingCarrierId' => 'required|exists:shipping_carriers,id',
        'paymentMethodId' => 'required|exists:payment_methods,id',
    ];

    public function mount()
    {
        $user = auth()->user();

        $this->address = new UserAddress;
        if ($user->addresses()->count()) {
            $this->address = $user->addresses()->orderBy('favorite', 'DESC')->first();
        }

        $this->invoiceAddress = new UserInvoiceAddress();
    }

    public function render()
    {
        $user = auth()->user();

        if ($this->addressId == -1) {
            $this->address = new UserAddress;
        } elseif ($this->addressId) {
            $this->address = $user->addresses()->where('id', $this->addressId)->first();
        }

        $this->invoiceAddress = new UserInvoiceAddress;
        if ($this->invoiceAddressId == -1) {
            $this->showInvoiceForm = false;
        } elseif ($this->invoiceAddressId) {
            $this->showInvoiceForm = true;
            if ($this->invoiceAddressId >= 0) {
                $this->invoiceAddress = $user->invoiceAddresses()->where('id', $this->invoiceAddressId)->first();
            }
        }

        $this->calculateTotalPrice();

        return view('livewire.shop.checkout')
            ->with('addresses', $user->addresses)
            ->with('invoiceAddresses', $user->invoiceAddresses)
            ->with('countries', Countries::all()->pluck('name.common', 'cca2')->toArray())
            ->with('shippingCarriers', ShippingCarrier::enabled()->get())
            ->with('paymentMethods', PaymentMethod::enabled()->get());
    }

    public function save()
    {
        $this->validate();

        if ($this->addressId == -1) {
            auth()->user()->addresses()->save($this->address);
        }

        if (!$this->invoiceAddressId == -2) {
            auth()->user()->invoiceAddresses()->save($this->invoiceAddress);
        }

        $order = Order::create(
            auth()->user(),
            $this->address,
            $this->invoiceAddress,
            ShippingCarrier::find($this->shippingCarrierId),
            PaymentMethod::find($this->paymentMethodId),
            CartHelper::get()
        );

        redirect()->route('orders.pay', ['order' => $order]);
    }

    public function calculateTotalPrice()
    {
        $this->taxes = CartHelper::getTotalTaxes();
        $this->price = CartHelper::getTotalPrice();

        if ($this->shippingCarrierId) {
            $shippingCarrier = ShippingCarrier::find($this->shippingCarrierId);
            if ($shippingCarrier) {
                $price = $shippingCarrier->price;
                $tax = $shippingCarrier->price * TaxesHelper::getTaxRatio();
                if (!TaxesHelper::productPricesContainTaxes()) {
                    $price += $tax;
                }
                $this->price += $price;
                $this->taxes += $tax;
            }
        }

        if ($this->paymentMethodId) {
            $paymentMethod = PaymentMethod::find($this->paymentMethodId);
            if ($paymentMethod) {
                $price = $paymentMethod->price;
                $tax = $paymentMethod->price * TaxesHelper::getTaxRatio();
                if (!TaxesHelper::productPricesContainTaxes()) {
                    $price += $tax;
                }
                $this->price += $price;
                $this->taxes += $tax;
            }
        }
    }
}
