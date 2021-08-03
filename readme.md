# UnzerDirect Payment Plugin for Shopware #
This plugin enables UnzerDirect as payment option for Shopware
## Installation ##
The plugin can easily be installed by following the steps below:
- Donwload the latest ziped version from the releases page
- Go to Extensions->My Extensions in the Shopware 6 Admin Panel
- Upload the zip-file and activate the extension
- Continue with configuring the plugin

## Updating ##
To update the plugin follow these steps
- Download the latest release
- Upload it using the Admin-Extensions page
- Click on the update options in the extensions context menu

## Configuration ##
After installing the plugin Shopware offers the possibility to configure it by clicking on the 3 dots and selecting the configuration option.
The UnzerDirect Payment plugin has the following settings:

|  Name        | Description                                   |
| ------------ | --------------------------------------------- |
|  Public Key  | The API key for the UnzerDirect integration      |
|  Private Key | The private key for the UnzerDirect integration  |
|  Branding ID | The branding ID for your UnzerDirect page  |
|  Test mode   | Configure wether the test mode is enabled. With test mode enabled payments using the UnzerDirect [test data](https://learn.unzerdirect.net/tech-talk/appendixes/test/ "test data") are possible.  |


The public and private key can be found in the UnzerDirect management panel under Settings->Integration (Use the API-Key for the public key configuration)

In order to use the UnzerDirect payment method the it has to be activated using the Payment settings. Don't forget to assign the payment method to the sales channel too.

## Administration functionality ##
The following actions can be performed in the Shopware administration:

#### Orders List ####
The plugin adds an additional column to the list or orders in the Shopware 6 administration. If the UnzerDirect payment status of an order allows capturing this column will contain an icon-button indicating this possibility. Upon clicking the icon a confimation window will be opened. After entering the amount to be captured (or leaving the preselected full amount) the capture can be confirmed and will be sent to the UnzerDirect API

#### UnzerDirect panel ####
When opening the detail view for an order in the administration a new UnzerDirect tab has been added at the top. Selecting it will lead to the UnzerDirect panel for the order.

This panel contains a List containing the History of the UnzerDirect payment. That means every requested operation by the user (capture/cancel/refund) and every callback response from the UnzerDirect server is logged and displayed there.

In addition above this list the following four buttons are present:

| Button   | Functionality                                      |
| -------- | -------------------------------------------------- |
| Capture  | Send a capture request to the UnzerDirect API         |
| Cancel   | Cancel a payment that has not been captured yet    |
| Refund   | Refund a payment that has already been captured    |
| Reload   | Refresh the history and the status of the payment  |


Each button is enabled or disabled according to the current status of the UnzerDirect payment.
Clicking either of the first three buttons will open a window to confirm this operation. When capturing or refunding partial the amount can be entered to make partial captures/refunds a possibility.
