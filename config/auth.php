<?php

use App\Models\User;

return [

    /*
    |--------------------------------------------------------------------------
    | Authentication Defaults
    |--------------------------------------------------------------------------
    |
    | This option defines the default authentication "guard" and password
    | reset "broker" for your application. You may change these values
    | as required, but they're a perfect start for most applications.
    |
    */

    'defaults' => [
        'guard' => env('AUTH_GUARD', 'web'),
        'passwords' => env('AUTH_PASSWORD_BROKER', 'users'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Authentication Guards
    |--------------------------------------------------------------------------
    |
    | Next, you may define every authentication guard for your application.
    | Of course, a great default configuration has been defined for you
    | which utilizes session storage plus the Eloquent user provider.
    |
    | All authentication guards have a user provider, which defines how the
    | users are actually retrieved out of your database or other storage
    | system used by the application. Typically, Eloquent is utilized.
    |
    | Supported: "session"
    |
    */

    'guards' => [
        'web' => [
            'driver' => 'session',
            'provider' => 'users',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | User Providers
    |--------------------------------------------------------------------------
    |
    | All authentication guards have a user provider, which defines how the
    | users are actually retrieved out of your database or other storage
    | system used by the application. Typically, Eloquent is utilized.
    |
    | If you have multiple user tables or models you may configure multiple
    | providers to represent the model / table. These providers may then
    | be assigned to any extra authentication guards you have defined.
    |
    | Supported: "database", "eloquent"
    |
    */

    'providers' => [
        'users' => [
            'driver' => 'eloquent',
            'model' => env('AUTH_MODEL', User::class),
        ],

        // 'users' => [
        //     'driver' => 'database',
        //     'table' => 'users',
        // ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Resetting Passwords
    |--------------------------------------------------------------------------
    |
    | These configuration options specify the behavior of Laravel's password
    | reset functionality, including the table utilized for token storage
    | and the user provider that is invoked to actually retrieve users.
    |
    | The expiry time is the number of minutes that each reset token will be
    | considered valid. This security feature keeps tokens short-lived so
    | they have less time to be guessed. You may change this as needed.
    |
    | The throttle setting is the number of seconds a user must wait before
    | generating more password reset tokens. This prevents the user from
    | quickly generating a very large amount of password reset tokens.
    |
    */

    'passwords' => [
        'users' => [
            'provider' => 'users',
            'table' => env('AUTH_PASSWORD_RESET_TOKEN_TABLE', 'password_reset_tokens'),
            'expire' => 60,
            'throttle' => 60,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Password Confirmation Timeout
    |--------------------------------------------------------------------------
    |
    | Here you may define the number of seconds before a password confirmation
    | window expires and users are asked to re-enter their password via the
    | confirmation screen. By default, the timeout lasts for three hours.
    |
    */

    'password_timeout' => env('AUTH_PASSWORD_TIMEOUT', 10800),

    /*
    |--------------------------------------------------------------------------
    | Super Administrator Initial Seed Configuration
    |--------------------------------------------------------------------------
    |
    | Digunakan oleh SuperAdminSeeder untuk membuat atau memperbarui akun awal
    | Super Administrator di production melalui variabel environment.
    |
    */
    'super_admin' => [
        'name' => env('SUPER_ADMIN_NAME', 'Wakasek Sarpras'),
        'degree' => env('SUPER_ADMIN_DEGREE', 'S.Kom'),
        'email' => env('SUPER_ADMIN_EMAIL', 'wakasek@gmail.com'),
        'password' => env('SUPER_ADMIN_PASSWORD', 'wakasek123'),
        'birth_date' => env('SUPER_ADMIN_BIRTH_DATE', '1975-06-15'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Seed Demo Data Control
    |--------------------------------------------------------------------------
    |
    | Menentukan apakah akun, barang, dan transaksi demo perlu di-seed.
    | Di environment production, nilainya default ke false.
    |
    */
    'seed_demo_data' => env('SEED_DEMO_DATA', false),

    /*
    |--------------------------------------------------------------------------
    | Bootstrap Super Admin Pengguna
    |--------------------------------------------------------------------------
    |
    | Email wajib diisi administrator saat menjalankan
    | `php artisan user:bootstrap-admin`. Password awal diambil dari
    | BOOTSTRAP_USER_ADMIN_PASSWORD bila diisi (agar administrator dapat
    | membacanya kembali); jika kosong password dibuat acak. Nilainya hanya
    | dipakai sekali saat akun dibuat, tidak pernah ditulis ke log, dan akun
    | tetap wajib mengganti password pada login pertama.
    |
    */
    'bootstrap_user_admin' => [
        'name' => env('BOOTSTRAP_USER_ADMIN_NAME', 'Super Admin Pengguna'),
        'email' => env('BOOTSTRAP_USER_ADMIN_EMAIL'),
        'password' => env('BOOTSTRAP_USER_ADMIN_PASSWORD'),
    ],

];
