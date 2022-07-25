# Changelog

## [UNRELEASED] - 
### Added
- Possible support for steasycheckout module (Prestashop 1.7+)
- Call courier via API

## [1.2.7] - 2022-02-01
### Fixed
- Fixed an issue where id_shop was not assigned during store address creation

### Improved
- Store address autofills country code with prestashop set country code
- During store address creation sets as active address by default

## [1.2.6] - 2021-11-16 patch
### Fixed
- Fixed mismatched token that prevented displaying label registration result
- Fixed COD always Yes inside order block information

## [1.2.5] - Store country code fix
### Fixed
- Incorrectly displayed country code for store (always showed LT)

## [1.2.4] - COD Support on Pickuppoint shipment
### Added
- COD additional service is now supported by pickup shipment (product 2711)

### Updated
- itella-api to v2.3.4

## [1.2.3] - Admin AJAX
### Changed
- Admin AJAX calls are now done through module controller, this should fix cross origin issues with multishop having different URL's

## [1.2.2] - Prestashop 1.7.7 support
### Added
- Settings to switch terminal selector type (can be Map or dropdown with search)
- Shipment comment field in Itella form inside order (this will appear in shipment label)
- Support for Prestashop 1.7.7

### Fixed
- Release version not being set in main file
- Possibly fixed an issue where in quick-order module would not receive correct token after customer logins inside checkout
- Order status changing functions now correctly set as static

### Updated
- itella-api to v2.3.2

## [1.2.1] - 2021-02-01
### Updated
- itella-api to v2.3.1

### Changed
- applied changes by the itella-api v2.3.1 library

## [1.2.0] - 2021-01-20
### Fixed
- tracking number save

### Improved
- programic code
- created an order status change when a label is generated
- created ability to add tracking number to email template using {tracking_code} or tracking url using {tracking_url}

### Changed
- name "Itella" to "Smartpost"

### Updated
- translations
- itella-api to v2.3.0

## [1.1.5] - 2020-12-14
### Changed
- Module now sends "Call Courier" email using Prestashop mailing functionality.

## [1.1.4] - 2020-11-26
### Added
- Estonian localization
- Latvian localization
- Russian localization

## [1.1.3] - 2020-11-18
### Updated
- itella-mapping.js to v1.3.1
- itella-api to v2.2.5

## [1.1.2] - 2020-09-09
### Updated
- itella-mapping.js to v1.2.3

## [1.1.1] - 2020-06-05
### Changed
- Carrier names by default is now in lithuanian if LT language is found.

### Updated
- itella-mapping.js to v1.2.2
- itella-api to 2.2.3

## [1.1.0] - Finland
### Added
- Finland support
- Call carrier advanced settings for email subject (required to save) as well Estonia and Finland emails
- Changelog

### Fixed
- locations parsing in pickup templates

### Updated
- itella-mapping.js to v1.2.0
- itella-api to 2.2.1

## [1.0.0] - Initial release
