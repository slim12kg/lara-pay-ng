# Nigerian Payment Gateway Solution for Laravel 5
- Supports Multiple Gateways with a single API approach, meaning Integration is the same across board in code


*** Do Not Use, Currently being developed to be compatible with the latest version of Laravel.

Aiming to Integrate as much Payment Gateways As Possible


 Currently Supported

 - VoguePay
 - GTPay



Gateways Currently being Looked at (In development)

- WebPay
- CashEnvoy
- SimplePay


# To Install

Simply Run
```

    composer require dammyammy/lara-pay-ng

```

Next Add the Service Provider into your Providers Array in config/app.php
```

    'providers' => [

        /*
         * Laravel Framework Service Providers...
         */
        'Illuminate\Foundation\Providers\ArtisanServiceProvider',
        'Illuminate\Auth\AuthServiceProvider',

        ....

        'LaraPayNG\Providers\LaraPayNGServiceProvider',
    ],
```

Next Publish All Package Files
```

    php artisan vendor:publish

```
This Would create the following:

    1. A **PaymentController** in app/Http/Controllers/PaymentController.php
    (i.e This is a working Sample, you may need to change Namespace to match your Projects namespace)

    2. **Migrations** would be published to database/migrations

    3. 4 Views, matching the Controller
        - one with a simulated checkout out button
        - one is the proceed to pay page (Pay Now Button)
        - one is a success notification Url, Showing a Successful Transaction
        - one is a failure notification Url, Showing a Failed Transaction

Next Add the following to your routes page in app/Http/routes.php and edit as you seem fit, But be sure specified values match Your Config file
```

    Route::get('orders',  [
        'as' => 'orders',
        'uses' => 'PaymentController@orders'
    ]);


    Route::get('checkout',  [
        'as' => 'checkout',
        'uses' => 'PaymentController@checkout'
    ]);

    $successUrl = config('lara-pay-ng.gateways.routes.success_route');
    $successName = config('lara-pay-ng.gateways.routes.success_route_name');

    $failureUrl = config('lara-pay-ng.gateways.routes.failure_route');
    $failureName = config('lara-pay-ng.gateways.routes.failure_route_name');


    Route::post('/' . $successUrl . '/{mert_id}', [
        'as' => $successName,
        'uses' => 'PaymentController@success'
    ]);

    Route::post('/' . $failureUrl . '/{mert_id}', [
        'as' => $failureName,
        'uses' => 'PaymentController@failed'
    ]);

    Route::post('/payment-notification/{mert_id}', [
        'as' => 'payment-notification',
        'uses' => 'PaymentController@notification'
    ]);



```


#Remember to Disallow CSRF Token Verification for your payment routes

This is perhaps one of the use cases in which u need to absolutely do this.

Else When a Transaction Id is been sent back Your site would throw a TokenMismatchException

As the Gateway Provider is posting back, and doesn't have a token Generated from your app

Use the appropriate route endpoints if you did change the default names and urls


```

    <?php

    namespace App\Http\Middleware;

    use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as BaseVerifier;

    class VerifyCsrfToken extends BaseVerifier
    {
        /**
         * The URIs that should be excluded from CSRF verification.
         *
         * @var array
         */
        protected $except = [
            //

            'transaction-failed/*',
            'transaction-successful/*',
            'payment-notification/*',

        ];
    }
```

Next Migrate the Package Tables.
```

    php artisan migrate

```

Next, Test the default Controller by visiting /Orders and follow through.


The Important thing to note is, to Inject the Payment Functionality into a controller,
all you need do is to Inject the PaymentGatewayManager Class into that controller, and you
Instantly gain access to all the methods provided by the facades below.

```

    use LaraPayNG\Exceptions\UnknownPaymentGatewayException;
    use LaraPayNG\Managers\PaymentGatewayManager;

    class PaymentController extends Controller
    {
        /**
         * @var PaymentGateway
         */
        private $paymentGateway;


        /**
         * @param PaymentGatewayManager $paymentGateway
         */
        public function __construct(PaymentGatewayManager $paymentGateway)
        {
            $this->paymentGateway = $paymentGateway;
        }

        ....

    }



```



# Facades



    Facades         Namespace                                   When To Use Which Facade

    Pay::           \LaraPayNG\Facades\Pay            Use If You want To Swap Gateways Through Config.
    GTPay::         \LaraPayNG\Facades\GTPay          Use If You Specifically want GTPay Gateway.
    WebPay::        \LaraPayNG\Facades\WebPay         Use If You Specifically want WebPay Gateway.
    VoguePay::       \LaraPayNG\Facades\VoguePay      Use If You Specifically want VoguePay Gateway.





# Methods Available Via Facades / PaymentGatewayManager Injection


    Method                                                               What it is Meant For

    payButton($transactionId, $transactionData, $class, $buttonTitle)   To create Pay Now Button For Set Gateway In a View.
    logTransaction($transactionData)                                    To Store Data Transaction Being Made for future Reference.
    receiveTransactionResponse($transactionId, $mertId)                 To Get Transaction Response back from Gateway
    logResponse($transactionData)                                       To Store Transaction Response from Gateway.

    getDefaultDriver()                                                  To get Default Payment Gateway Driver At Runtime.
    setDefaultDriver($name)                                             To set Default Payment Gateway Driver At Runtime..
    config($key)                                                        Access Config Off Set Default Payment Gateway Driver.


# Exceptions
    All Exceptions Exist Under This Namespace;;

    ```php

        namespace \LaraPayNG\Exceptions;
    ```

    Exceptions                                  When It is Thrown

    UnknownPaymentGatewayException              If set Driver for Default Gateway is unknown or unsupported.
    UnspecifiedTransactionAmountException       If amount(WebPay) | gtpay_tranx_amt(GTPay) is not in $transactionData.
    UnspecifiedPayItemIdException               If pay_item_id is unspecifed in $transactionData array (GTPay)
    PaymentGatewayVerificationFailedException   If Hash Calculation is Wrong During Verification ( Applies to GTPay/WebPay Gateway ).



# Commands
    There is a Command to Help Clear Stale Records That have been logged within a set number of days

    To Use Simply Type in your terminal

    ```bash

        # php artisan lara-pay-ng:purge-database gatewayname --days=3 --with-failed=false
        php artisan lara-pay-ng:purge-database

    ```

    You can Pass the gatewayname attribute to the Command eg. gtpay, voguepay. This is particularly useful for Multi-gateway Setups
    In the event of not passing the option, the default gateway driver from set config would be used.

    It also can be passed a --with-failed option which accepts true/false. True means all Transactions that failed should be included in the deletion,
    as well as a --days option specifying how many days back data is considered old.



# Tips

        Add Methods Like These to Your Helpers.php File then Autoload It In Your composer.json

     ```php

         public function generateTransactionData($dessert, $transactionId)
         {
             return 'name=' . $dessert->present()->name . ';pre=' . $dessert->present()->buyPrice
             . ';buyer=' . currentUserName() . '; transactionId=' . $transactionId;
         }




         public function generateTransactionMemo($product)
         {
             return 'Name: ' . $product->name . '; Price: ' . $product->price
             . '; Buyer: ' . Auth::user()->email;
         }
     ```

     then You can easily Do

     ```php
        $product = Product::get('2');

        $transactionData = [
            'amount' => $product->price,
            'memo' => generateTransactionMemo($product),
            ...
        ];

        WebPay::payButton($product->id, $transactionData, 'btn btn-success', '<i class="fa fa-currency"></i> Pay Now');

     ```

  ## TODO

  - Refactor Code
  - Create a Simple Straightforward API
  - Ability to Handle Transaction From Start to Finish
  - Back everything up with Tests.
