# NETOPIA Payments Gateway for CS-Cart

  Payment gateway addon that integrates NETOPIA mobilPay with CS-Cart 4.18.x.

  ## Features

  - Visa and Mastercard credit/debit card payments
  - Test (sandbox) and Live mode support
  - Automatic order status updates via IPN (confirmed, authorized, failed, refund)
  - Encrypted communication using public certificate and private key
  - Multi-language support (EN, DE, RO)

  ## Requirements

  - CS-Cart 4.18.x
  - PHP 7.4+ / 8.1+
  - OpenSSL and DOM PHP extensions
  - NETOPIA merchant account with API credentials (signature, `.cer` and `.key` files)

  ## Installation

  1. Copy `app/addons/rv__netopia/` to your CS-Cart installation
  2. Copy `design/backend/templates/addons/rv__netopia/` to your CS-Cart installation
  3. Copy `var/langs/*/addons/rv__netopia.po` for your active languages
  4. Clear cache: `rm -rf var/cache/*`
  5. Enable the addon in **Admin > Add-ons**
  6. Configure payment method with your NETOPIA signature, `.cer` and `.key` files

  ## Configuration

  1. Go to **Admin > Payment methods**
  2. Add a new payment method and select **Netopia Payments** as the processor
  3. In the **Configure** tab:
     - Enter your **Account Signature** from the NETOPIA dashboard
     - Upload your **Public Certificate** (`.cer` file)
     - Upload your **Private Key** (`.key` file)
     - Select **Test** or **Live** mode

  ## How It Works

  1. Customer selects Netopia Payments at checkout
  2. Customer is redirected to the NETOPIA payment page
  3. After payment, NETOPIA sends an IPN (server-to-server) notification to update the order status
  4. Customer is redirected back to the store with the order confirmation

  ### Order Status Mapping

  | NETOPIA Action     | CS-Cart Status          |
  |--------------------|-------------------------|
  | `confirmed`        | Paid (P)                |
  | `paid`             | Open (O) — authorized   |
  | `confirmed_pending`| Open (O) — pending      |
  | `canceled`         | Incomplete (N)          |
  | `credit`           | Incomplete (N) — refund |
  | Error              | Failed (F)              |

  ## File Structure

  app/addons/rv__netopia/
  ├── addon.xml                          # Addon manifest (Schema 4.0)
  ├── config.php                         # Gateway URL constants
  ├── payments/rv__netopia.php           # Payment processor script
  └── src/
      ├── Bootstrap.php                  # Hook registration
      ├── ServiceProvider.php            # DI container setup
      ├── Installer.php                  # Install/uninstall routines
      ├── Encryption/
      │   └── NetopiaEncryption.php      # OpenSSL encrypt/decrypt
      ├── HookHandlers/
      │   └── PaymentsHookHandler.php    # Certificate upload handling
      ├── Payments/
      │   └── NetopiaGateway.php         # Gateway logic & IPN mapping
      └── Request/
          ├── PaymentRequest.php         # XML request builder
          └── PaymentNotify.php          # IPN XML parser

  design/backend/templates/addons/rv__netopia/
  └── views/payments/components/cc_processors/
      └── rv__netopia.tpl                # Admin configuration template

  var/langs/
  ├── en/addons/rv__netopia.po           # English translations
  ├── de/addons/rv__netopia.po           # German translations
  └── ro/addons/rv__netopia.po           # Romanian translations

  ## License

  MIT License — see [LICENSE](LICENSE) for details.
