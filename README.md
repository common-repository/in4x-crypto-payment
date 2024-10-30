# Introduction 
A Woocommerce payment plugin for IN4X Global Crypto Payment support

# Getting Started
Plugin can be installed by going to Wordpress and choosing "Add plugin" and uploading this folder as a zip.

# Build and Test
To create a zip file from archive use:
```
git archive --format=zip --prefix=in4x-crypto-payment/ HEAD -o in4x-crypto-payment.zip
```
Plugin is usually available on both servers: [Prod](https://www.in4xglobal.com/in4x-crypto-payment.zip) [Dev](https://dev.in4xglobal.com/in4x-crypto-payment.zip)

# Plugin Configuration
For the plugin to function, the client needs to do the following:

```
Go to Woocommerce --> Settings --> Checkout (normally https://www.somesite.com/wp-admin/admin.php?page=wc-settings&tab=checkout)
IN4X Crypto Payment --> Setup/Manage
```

Then configure the plugin.

Settings:
1. On/Off plugin --> Enables and disables payment via IN4X.
2. On/Off test mode --> Enables and disables test mode (all test mode requests go to dev.in4xglobal.com)
3. Title --> Title of payment method as visible to the paying client.
4. Description --> Description of the payment method as visible to the paying client.
5. Response server URL --> The Webhook URL which IN4X Backend server uses to update Woocommerce order statuses.
6. Public API Key --> Partner public API key.
7. Secret API Key --> Partner private API secret (this is how the plugin verifies the IN4X Server)

> API keys differ between production and test environments