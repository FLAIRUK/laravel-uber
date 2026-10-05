<?php

namespace FLAIRUK\Uber\Tests;

use Closure;
use FLAIRUK\Uber\Facades\Uber;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

/**
 * Every wrapped endpoint sends the documented method to the documented path,
 * with an app token for the documented scope or the user's token.
 */
class EndpointsTest extends TestCase
{
    /**
     * @return array<string, array{0: string, 1: string, 2: string|null, 3: Closure}>
     *                                                                                [method, path (with query), app scope or null for a user token, call]
     */
    public static function endpoints(): array
    {
        $user = fn () => Uber::withToken('user-token');

        return [
            // Riders
            'rides products' => ['GET', '/v1.2/products?latitude=51.5&longitude=-0.1', null, fn () => $user()->rides()->products(51.5, -0.1)],
            'rides product' => ['GET', '/v1.2/products/p1', null, fn () => $user()->rides()->product('p1')],
            'rides price estimates' => ['GET', '/v1.2/estimates/price?start_latitude=1&start_longitude=2&end_latitude=3&end_longitude=4', null, fn () => $user()->rides()->priceEstimates(1, 2, 3, 4)],
            'rides time estimates' => ['GET', '/v1.2/estimates/time?start_latitude=1&start_longitude=2&product_id=p1', null, fn () => $user()->rides()->timeEstimates(1, 2, 'p1')],
            'rides estimate' => ['POST', '/v1.2/requests/estimate', null, fn () => $user()->rides()->estimate(['product_id' => 'p1'])],
            'rides request' => ['POST', '/v1.2/requests', null, fn () => $user()->rides()->request(['fare_id' => 'f'])],
            'rides current' => ['GET', '/v1.2/requests/current', null, fn () => $user()->rides()->current()],
            'rides find' => ['GET', '/v1.2/requests/r1', null, fn () => $user()->rides()->find('r1')],
            'rides update' => ['PATCH', '/v1.2/requests/r1', null, fn () => $user()->rides()->update('r1', ['end_latitude' => 1])],
            'rides cancel' => ['DELETE', '/v1.2/requests/r1', null, fn () => $user()->rides()->cancel('r1')],
            'rides cancel current' => ['DELETE', '/v1.2/requests/current', null, fn () => $user()->rides()->cancelCurrent()],
            'rides map' => ['GET', '/v1.2/requests/r1/map', null, fn () => $user()->rides()->map('r1')],
            'rides receipt' => ['GET', '/v1.2/requests/r1/receipt', null, fn () => $user()->rides()->receipt('r1')],
            'rides me' => ['GET', '/v1.2/me', null, fn () => $user()->rides()->me()],
            'rides promotion' => ['PATCH', '/v1.2/me', null, fn () => $user()->rides()->applyPromotion('FREE')],
            'rides history' => ['GET', '/v1.2/history?offset=0&limit=50', null, fn () => $user()->rides()->history()],
            'rides payment methods' => ['GET', '/v1.2/payment-methods', null, fn () => $user()->rides()->paymentMethods()],
            'rides place' => ['GET', '/v1.2/places/home', null, fn () => $user()->rides()->place('home')],
            'rides update place' => ['PUT', '/v1.2/places/work', null, fn () => $user()->rides()->updatePlace('work', '1 High St')],

            // Guest Rides
            'guests estimates' => ['POST', '/v1/guests/trips/estimates', 'guests.trips', fn () => Uber::guestRides()->estimates(['pickup' => []])],
            'guests create' => ['POST', '/v1/guests/trips', 'guests.trips', fn () => Uber::guestRides()->create(['product_id' => 'p'])],
            'guests trips' => ['GET', '/v1/guests/trips?trip_status=PAST&limit=10', 'guests.trips', fn () => Uber::guestRides()->trips('PAST', 10)],
            'guests find' => ['GET', '/v1/guests/trips/t1', 'guests.trips', fn () => Uber::guestRides()->find('t1')],
            'guests update' => ['PUT', '/v1/guests/trips/t1', 'guests.trips', fn () => Uber::guestRides()->update('t1', ['dropoff' => []])],
            'guests cancel' => ['DELETE', '/v1/guests/trips/t1', 'guests.trips', fn () => Uber::guestRides()->cancel('t1')],
            'guests receipt' => ['GET', '/v1/guests/trips/t1/receipt', 'guests.trips', fn () => Uber::guestRides()->receipt('t1')],
            'guests dispatch' => ['POST', '/v1/guests/trips/t1/dispatch', 'guests.trips', fn () => Uber::guestRides()->dispatch('t1')],
            'guests message' => ['POST', '/v1/guests/trips/t1/message', 'guests.trips', fn () => Uber::guestRides()->message('t1', 'Hi')],
            'guests communication' => ['PUT', '/v1/guests/trips/t1/communication', 'guests.trips', fn () => Uber::guestRides()->communication('t1', '+447700900000')],
            'guests call' => ['POST', '/v1/guests/trips/call', 'guests.trips', fn () => Uber::guestRides()->call('t1', '+447700900000')],
            'guests tip' => ['POST', '/v1/guests/trips/tip', 'guests.trips', fn () => Uber::guestRides()->tip('t1', 5.0)],
            'guests zones' => ['GET', '/v1/guests/zones?latitude=1&longitude=2', 'guests.trips', fn () => Uber::guestRides()->zones(1, 2)],
            'guests autocomplete' => ['GET', '/v1/guests/address/autocomplete?query=High%20St', 'guests.trips', fn () => Uber::guestRides()->autocomplete('High St')],
            'guests phone info' => ['GET', '/v1/guests/guests/phone-info?phone_number=%2B447700900000', 'guests.trips', fn () => Uber::guestRides()->phoneInfo('+447700900000')],
            'guests products info' => ['POST', '/v1/trips/products-info', 'guests.trips', fn () => Uber::guestRides()->productsInfo(['point' => []])],

            // Health
            'health estimates' => ['POST', '/v1/health/trips/estimates', 'health', fn () => Uber::health()->estimates([])],
            'health create' => ['POST', '/v1/health/trips', 'health', fn () => Uber::health()->create([])],
            'health find' => ['GET', '/v1/health/trips/t1', 'health', fn () => Uber::health()->find('t1')],
            'health communication' => ['PUT', '/v1/health/trips/communication', 'health', fn () => Uber::health()->communication('t1', '+447700900000')],
            'health phone info' => ['GET', '/v1/health/guests/phone-info?phone_number=%2B447700900000', 'health', fn () => Uber::health()->phoneInfo('+447700900000')],
            'health zones' => ['GET', '/v1/health/zones?latitude=1&longitude=2', 'health', fn () => Uber::health()->zones(1, 2)],
            'health widget token' => ['POST', '/u4b/v1/widget/transienttoken', 'health', fn () => Uber::health()->widgetToken('u1')],

            // Direct
            'direct quote' => ['POST', '/v1/customers/cust-1/delivery_quotes', 'eats.deliveries', fn () => Uber::direct()->quote(['pickup_address' => 'a', 'dropoff_address' => 'b'])],
            'direct create' => ['POST', '/v1/customers/cust-1/deliveries', 'eats.deliveries', fn () => Uber::direct()->create([])],
            'direct list' => ['GET', '/v1/customers/cust-1/deliveries?filter=pending&Offset=10', 'eats.deliveries', fn () => Uber::direct()->list(['filter' => 'pending', 'offset' => 10])],
            'direct find' => ['GET', '/v1/customers/cust-1/deliveries/del_1', 'eats.deliveries', fn () => Uber::direct()->find('del_1')],
            'direct update' => ['POST', '/v1/customers/cust-1/deliveries/del_1', 'eats.deliveries', fn () => Uber::direct()->update('del_1', ['tip_by_customer' => 100])],
            'direct cancel' => ['POST', '/v1/customers/cust-1/deliveries/del_1/cancel', 'eats.deliveries', fn () => Uber::direct()->cancel('del_1')],
            'direct proof' => ['POST', '/v1/customers/cust-1/deliveries/del_1/proof-of-delivery', 'eats.deliveries', fn () => Uber::direct()->proofOfDelivery('del_1')],
            'direct stores' => ['GET', '/v1/direct/organizations/cust-1/stores?latitude=1&longitude=2', 'eats.deliveries', fn () => Uber::direct()->stores(1, 2)],
            'direct refund' => ['POST', '/v1/direct/cust-1/submit_refund', 'eats.deliveries', fn () => Uber::direct()->refund(['delivery_id' => 'del_1'])],
            'direct org create' => ['POST', '/v1/direct/organizations', 'direct.organizations', fn () => Uber::direct()->organizations()->create([])],
            'direct org find' => ['GET', '/v1/direct/organizations/o1', 'direct.organizations', fn () => Uber::direct()->organizations()->find('o1')],
            'direct org invite' => ['POST', '/v1/direct/organizations/o1/memberships/invite', 'direct.organizations', fn () => Uber::direct()->organizations()->invite('o1', [])],
            'direct locations' => ['GET', '/v1/direct/organizations/cust-1/business_locations?limit=5', 'direct.organizations', fn () => Uber::direct()->businessLocations()->list(5)],
            'direct location' => ['GET', '/v1/direct/organizations/o1/business_locations/b1', 'direct.organizations', fn () => Uber::direct()->businessLocations('o1')->find('b1')],
            'direct location update' => ['PATCH', '/v1/direct/organizations/o1/business_locations/b1', 'direct.organizations', fn () => Uber::direct()->businessLocations('o1')->update('b1', ['name' => 'x'])],

            // Eats
            'eats stores' => ['GET', '/v1/delivery/stores?page_size=20', 'eats.store', fn () => Uber::eats()->stores()->list(20)],
            'eats store' => ['GET', '/v1/delivery/store/s1?expand=MENU_INFO', 'eats.store', fn () => Uber::eats()->stores()->find('s1', ['MENU_INFO'])],
            'eats store update' => ['POST', '/v1/delivery/store/s1', 'eats.store', fn () => Uber::eats()->stores()->update('s1', ['contact' => []])],
            'eats store status' => ['GET', '/v1/delivery/store/s1/status', 'eats.store', fn () => Uber::eats()->stores()->status('s1')],
            'eats store set status' => ['POST', '/v1/delivery/store/s1/update-store-status', 'eats.store', fn () => Uber::eats()->stores()->setStatus('s1', 'OFFLINE')],
            'eats prep time' => ['POST', '/v1/delivery/store/s1/update-store-prep-time', 'eats.store', fn () => Uber::eats()->stores()->setPrepTime('s1', ['default_prep_time' => 900])],
            'eats fulfilment' => ['POST', '/v1/delivery/store/s1/update-fulfillment-configuration', 'eats.byoc.fulfillment.config', fn () => Uber::eats()->stores()->setFulfillmentConfiguration('s1', [])],
            'eats holiday hours' => ['GET', '/v1/eats/stores/s1/holiday-hours', 'eats.store', fn () => Uber::eats()->stores()->holidayHours('s1')],
            'eats set holiday hours' => ['POST', '/v1/eats/stores/s1/holiday-hours', 'eats.store', fn () => Uber::eats()->stores()->setHolidayHours('s1', [])],
            'eats menu' => ['GET', '/v2/eats/stores/s1/menus', 'eats.store', fn () => Uber::eats()->menus()->find('s1')],
            'eats menu item' => ['POST', '/v2/eats/stores/s1/menus/items/i1', 'eats.store', fn () => Uber::eats()->menus()->updateItem('s1', 'i1', [])],
            'eats order' => ['GET', '/v1/delivery/order/o1?expand=carts%2Cpayment', 'eats.order', fn () => Uber::eats()->orders()->find('o1', ['carts', 'payment'])],
            'eats store orders' => ['GET', '/v1/delivery/store/s1/orders?state=OFFERED', 'eats.order', fn () => Uber::eats()->orders()->forStore('s1', ['state' => 'OFFERED'])],
            'eats accept' => ['POST', '/v1/delivery/order/o1/accept', 'eats.order', fn () => Uber::eats()->orders()->accept('o1')],
            'eats deny' => ['POST', '/v1/delivery/order/o1/deny', 'eats.order', fn () => Uber::eats()->orders()->deny('o1', ['type' => 'STORE_CLOSED'])],
            'eats cancel' => ['POST', '/v1/delivery/order/o1/cancel', 'eats.order', fn () => Uber::eats()->orders()->cancel('o1')],
            'eats ready' => ['POST', '/v1/delivery/order/o1/ready', 'eats.order', fn () => Uber::eats()->orders()->ready('o1')],
            'eats ready time' => ['POST', '/v1/delivery/order/o1/update-ready-time', 'eats.order', fn () => Uber::eats()->orders()->updateReadyTime('o1', '2026-10-05T12:00:00Z')],
            'eats adjust price' => ['POST', '/v1/delivery/order/o1/adjust-price', 'eats.order', fn () => Uber::eats()->orders()->adjustPrice('o1', ['amount_e5' => 100000])],
            'eats validate item' => ['POST', '/v1/delivery/order/o1/validate-item-fulfillment', 'eats.order', fn () => Uber::eats()->orders()->validateItemFulfillment('o1', [])],
            'eats resolve issues' => ['POST', '/v1/delivery/order/o1/resolve-fulfillment-issues', 'eats.order', fn () => Uber::eats()->orders()->resolveFulfillmentIssues('o1', [])],
            'eats replacements' => ['POST', '/v1/delivery/get-replacement-recommendations', 'eats.order', fn () => Uber::eats()->orders()->replacementRecommendations([])],
            'eats courier count' => ['POST', '/v1/delivery/order/o1/update-delivery-partner-count', 'delivery.multiple.courier', fn () => Uber::eats()->orders()->setCourierCount('o1', 2)],
            'eats legacy find' => ['GET', '/v2/eats/order/o1', 'eats.order', fn () => Uber::eats()->legacyOrders()->find('o1')],
            'eats legacy created' => ['GET', '/v1/eats/stores/s1/created-orders?limit=5', 'eats.store.orders.read', fn () => Uber::eats()->legacyOrders()->created('s1', 5)],
            'eats legacy canceled' => ['GET', '/v1/eats/stores/s1/canceled-orders', 'eats.store.orders.read', fn () => Uber::eats()->legacyOrders()->canceled('s1')],
            'eats legacy accept' => ['POST', '/v1/eats/orders/o1/accept_pos_order', 'eats.order', fn () => Uber::eats()->legacyOrders()->accept('o1')],
            'eats legacy deny' => ['POST', '/v1/eats/orders/o1/deny_pos_order', 'eats.order', fn () => Uber::eats()->legacyOrders()->deny('o1', ['code' => 'STORE_CLOSED'])],
            'eats legacy cancel' => ['POST', '/v1/eats/orders/o1/cancel', 'eats.order', fn () => Uber::eats()->legacyOrders()->cancel('o1', 'OTHER')],
            'eats restaurant delivery' => ['POST', '/v1/eats/orders/o1/restaurantdelivery/status', 'eats.store.orders.restaurantdelivery.status', fn () => Uber::eats()->legacyOrders()->restaurantDeliveryStatus('o1', 'arriving')],
            'eats integration find' => ['GET', '/v1/eats/stores/s1/pos_data', 'eats.store', fn () => Uber::eats()->integrations()->find('s1')],
            'eats integration update' => ['PATCH', '/v1/eats/stores/s1/pos_data', 'eats.store', fn () => Uber::eats()->integrations()->update('s1', ['integration_enabled' => false])],
            'eats integration remove' => ['DELETE', '/v1/eats/stores/s1/pos_data', 'eats.pos_provisioning', fn () => Uber::eats()->integrations()->remove('s1')],
            'eats promotion create' => ['POST', '/v1/delivery/stores/s1/promotion', 'eats.store.promotion.write', fn () => Uber::eats()->promotions()->create('s1', [])],
            'eats promotion find' => ['GET', '/v1/delivery/promotions/p1', 'eats.store.promotion.read', fn () => Uber::eats()->promotions()->find('p1')],
            'eats promotions' => ['GET', '/v1/delivery/stores/s1/promotions?state=active', 'eats.store.promotion.read', fn () => Uber::eats()->promotions()->forStore('s1', 'active')],
            'eats promotion revoke' => ['POST', '/v1/delivery/promotions/p1/revoke', 'eats.store.promotion.write', fn () => Uber::eats()->promotions()->revoke('p1')],
            'eats report' => ['POST', '/v1/eats/report', 'eats.report', fn () => Uber::eats()->reports()->create('ORDER_HISTORY_REPORT', '2026-09-01', '2026-09-30', ['s1'])],
            'eats plc v2 status' => ['POST', '/v2/plc/healthz', 'delivery.plc2', fn () => Uber::eats()->payments()->status()],
            'eats plc v2 validate' => ['POST', '/v2/plc/payment_codes', 'delivery.plc2', fn () => Uber::eats()->payments()->validate('code', 'acceptor')],
            'eats plc v2 charge' => ['POST', '/v2/plc/charges', 'delivery.plc2', fn () => Uber::eats()->payments()->charge(['payment_code' => 'c'])],
            'eats plc v2 refund' => ['POST', '/v2/plc/refunds', 'delivery.plc2', fn () => Uber::eats()->payments()->refund([])],
            'eats plc v1 status' => ['GET', '/v1/plc/healthz', 'delivery.plc', fn () => Uber::eats()->payments(1)->status()],
            'eats plc v1 validate' => ['GET', '/v1/plc/payment_codes/code?acceptor_code=a', 'delivery.plc', fn () => Uber::eats()->payments(1)->validate('code', 'a')],
            'eats plc v1 charges' => ['GET', '/v1/plc/charges?reference_id=r', 'delivery.plc', fn () => Uber::eats()->payments(1)->charges(['reference_id' => 'r'])],
            'eats plc v1 charge' => ['GET', '/v1/plc/charges/ch1', 'delivery.plc', fn () => Uber::eats()->payments(1)->findCharge('ch1')],
            'eats byoc location' => ['POST', '/v1/eats/byoc/restaurants/orders/event/location', 'eats.byoc.position', fn () => Uber::eats()->couriers()->location([])],

            // Business
            'business receipt' => ['GET', '/v1/business/orders/o1/receipt', 'business.receipts', fn () => Uber::business()->receipts('org-1')->find('o1')],
            'business trip receipt' => ['GET', '/v1/business/trips/t1/receipt', 'business.receipts', fn () => Uber::business()->receipts('org-1')->trip('t1')],
            'business trip pdf' => ['GET', '/v1/business/trips/t1/receipt/pdf_url', 'business.receipts', fn () => Uber::business()->receipts('org-1')->tripPdfUrl('t1')],
            'business trip invoices' => ['GET', '/v1/business/trips/t1/invoice_urls', 'business.receipts', fn () => Uber::business()->receipts('org-1')->tripInvoiceUrls('t1')],
            'vouchers template create' => ['POST', '/v1/organizations/org-1/voucher-program-templates', 'organizations.voucher_programs', fn () => Uber::business()->vouchers('org-1')->createTemplate([])],
            'vouchers templates' => ['GET', '/v1/organizations/org-1/voucher-program-templates?limit=10', 'organizations.voucher_programs', fn () => Uber::business()->vouchers('org-1')->templates(10)],
            'vouchers template' => ['GET', '/v1/organizations/org-1/voucher-program-templates/tp1', 'organizations.voucher_programs', fn () => Uber::business()->vouchers('org-1')->template('tp1')],
            'vouchers template update' => ['PATCH', '/v1/organizations/org-1/voucher-program-templates/tp1', 'organizations.voucher_programs', fn () => Uber::business()->vouchers('org-1')->updateTemplate('tp1', [])],
            'vouchers create' => ['POST', '/v1/organizations/org-1/voucher-programs', 'organizations.voucher_programs', fn () => Uber::business()->vouchers('org-1')->create([])],
            'vouchers from template' => ['POST', '/v1/organizations/org-1/voucher-programs/create-from-template', 'organizations.voucher_programs', fn () => Uber::business()->vouchers('org-1')->createFromTemplate([])],
            'vouchers individual' => ['POST', '/v1/organizations/org-1/individual_voucher_program/create', 'organizations.voucher_programs', fn () => Uber::business()->vouchers('org-1')->createIndividual([])],
            'vouchers find' => ['GET', '/v1/organizations/org-1/voucher-programs/vp1?include_guests=true', 'organizations.voucher_programs', fn () => Uber::business()->vouchers('org-1')->find('vp1', true)],
            'vouchers update' => ['PATCH', '/v1/organizations/org-1/voucher-programs/vp1', 'organizations.voucher_programs', fn () => Uber::business()->vouchers('org-1')->update('vp1', [])],
            'vouchers cancel' => ['POST', '/v1/organizations/org-1/voucher-programs/vp1/cancel', 'organizations.voucher_programs', fn () => Uber::business()->vouchers('org-1')->cancel('vp1')],
            'vouchers search' => ['POST', '/v1/organizations/org-1/voucher-programs/search', 'organizations.voucher_programs', fn () => Uber::business()->vouchers('org-1')->search('Active')],
            'vouchers guests' => ['GET', '/v1/organizations/org-1/voucher-programs/vp1/guests', 'organizations.voucher_programs', fn () => Uber::business()->vouchers('org-1')->guests('vp1')],
            'vouchers add guests' => ['POST', '/v1/organizations/org-1/voucher-programs/vp1/add-guests', 'organizations.voucher_programs', fn () => Uber::business()->vouchers('org-1')->addGuests('vp1', [['email' => 'a@b.c']])],
            'vouchers generate codes' => ['POST', '/v1/organizations/org-1/voucher-programs/vp1/codes/generate', 'organizations.voucher_programs', fn () => Uber::business()->vouchers('org-1')->generateCodes('vp1', 5)],
            'vouchers codes' => ['GET', '/v1/organizations/org-1/voucher-programs/vp1/codes', 'organizations.voucher_programs', fn () => Uber::business()->vouchers('org-1')->codes('vp1')],
            'vouchers cancel codes' => ['POST', '/v1/organizations/org-1/voucher-programs/vp1/codes/cancel', 'organizations.voucher_programs', fn () => Uber::business()->vouchers('org-1')->cancelCodes('vp1', ['A'])],
            'vouchers distribute' => ['POST', '/v1/organizations/org-1/voucher-programs/vp1/codes/distribute', 'organizations.voucher_programs', fn () => Uber::business()->vouchers('org-1')->distributeCodes('vp1', [])],
            'vouchers redeem' => ['POST', '/v1.2/me/vouchers/redeem', null, fn () => $user()->business()->vouchers('org-1')->redeem('CODE')],
            'business org create' => ['POST', '/v1/organizations', 'business.organizations', fn () => Uber::business()->organizations()->create([])],
            'business org find' => ['GET', '/v1/organizations/org-1', 'business.organizations', fn () => Uber::business()->organizations()->find('org-1')],
            'business org remove' => ['DELETE', '/v1/organizations/org-1', 'business.organizations', fn () => Uber::business()->organizations()->remove('org-1')],
            'business org programs' => ['GET', '/v1/organizations/org-1/programs?program_category=RIDES', 'business.organizations', fn () => Uber::business()->organizations()->programs('org-1', category: 'RIDES')],
            'employees create' => ['POST', '/v1/business/organizations/org-1/employees', 'business.employees', fn () => Uber::business()->employees('org-1')->create([])],
            'employees update' => ['POST', '/v1/business/organizations/org-1/employees/update', 'business.employees', fn () => Uber::business()->employees('org-1')->update([])],
            'employees find' => ['GET', '/v1/business/organizations/org-1/employee?email=a%40b.c', 'business.employees', fn () => Uber::business()->employees('org-1')->find('a@b.c')],
            'employees list' => ['GET', '/v1/business/organizations/org-1/employees?limit=1000', 'business.employees', fn () => Uber::business()->employees('org-1')->list()],
            'employees search' => ['GET', '/v1/business/organizations/org-1/employees/search?email=a%40b.c', 'business.employees', fn () => Uber::business()->employees('org-1')->search(['email' => 'a@b.c'])],
            'employees invite' => ['POST', '/v1/business/organizations/org-1/employees/invite', 'business.employees', fn () => Uber::business()->employees('org-1')->invite([])],
            'employees remove' => ['DELETE', '/v1/business/organizations/org-1/employees?email=a%40b.c', 'business.employees', fn () => Uber::business()->employees('org-1')->remove('a@b.c')],
            'employees groups' => ['GET', '/v1/business/organizations/org-1/groups', 'business.employees', fn () => Uber::business()->employees('org-1')->groups()],
            'employees save group' => ['POST', '/v1/business/organizations/org-1/group', 'business.employees', fn () => Uber::business()->employees('org-1')->saveGroup([])],
            'employees delete group' => ['DELETE', '/v1/business/organizations/org-1/group?group_id=g1', 'business.employees', fn () => Uber::business()->employees('org-1')->deleteGroup('g1')],
            'employee trips programs' => ['POST', '/v2/employees/programs', 'business.trips', fn () => Uber::business()->employeeTrips('org-1')->programs('a@b.c')],
            'employee trips estimates' => ['POST', '/v2/employees/trips/estimates', 'business.trips', fn () => Uber::business()->employeeTrips('org-1')->estimates([])],
            'employee trips zones' => ['GET', '/v2/employees/zones?latitude=1&longitude=2', 'business.trips', fn () => Uber::business()->employeeTrips('org-1')->zones(1, 2)],
            'employee trips autocomplete' => ['GET', '/v2/employees/address/autocomplete?query=High', 'business.trips', fn () => Uber::business()->employeeTrips('org-1')->autocomplete('High')],
            'employee trips create' => ['POST', '/v2/employees/trips', 'business.trips', fn () => Uber::business()->employeeTrips('org-1')->create([])],
            'employee trips find' => ['GET', '/v2/employees/trips/r1', 'business.trips', fn () => Uber::business()->employeeTrips('org-1')->find('r1')],
            'employee trips update' => ['PUT', '/v2/employees/trips?trip_uuid=r1', 'business.trips', fn () => Uber::business()->employeeTrips('org-1')->update('r1', [])],
            'employee trips cancel' => ['DELETE', '/v2/employees/trips/r1', 'business.trips', fn () => Uber::business()->employeeTrips('org-1')->cancel('r1')],
            'employee trips search' => ['POST', '/v1/trips/search', 'business.trips', fn () => Uber::business()->employeeTrips('org-1')->search([])],
            'statements find' => ['GET', '/v1/business/statements/st1?organization_uuid=org-1', 'business.statements', fn () => Uber::business()->statements('org-1')->find('st1')],

            // Identity
            'identity me' => ['GET', '/v3/me', null, fn () => $user()->identity()->me()],
            'identity link' => ['POST', '/v1/identity/link-account', null, fn () => $user()->identity()->linkAccount('u1')],
            'identity unlink' => ['POST', '/v1/identity/unlink-account', 'identity.unlink-account', fn () => Uber::identity()->unlinkAccount('u1')],
            'identity register client' => ['POST', '/v2/oauth/register', 'oauth.dcr.b2b', fn () => Uber::identity()->registerClient([])],
            'identity create unit' => ['POST', '/v1/identity/organizations/org-1/orgunits', 'identity.organizations', fn () => Uber::identity()->organization('org-1')->createUnit([])],
            'identity units' => ['GET', '/v1/identity/organizations/org-1/orgunits?limit=10', 'identity.organizations', fn () => Uber::identity()->organization('org-1')->units(10)],
            'identity unit' => ['GET', '/v1/identity/organizations/org-1/orgunits/u1', 'identity.organizations', fn () => Uber::identity()->organization('org-1')->unit('u1')],
            'identity update unit' => ['PUT', '/v1/identity/organizations/org-1/orgunits/u1', 'identity.organizations', fn () => Uber::identity()->organization('org-1')->updateUnit('u1', [])],
            'identity delete unit' => ['DELETE', '/v1/identity/organizations/org-1/orgunits/u1', 'identity.organizations', fn () => Uber::identity()->organization('org-1')->deleteUnit('u1')],
            'identity create user' => ['POST', '/v1/identity/organizations/org-1/orgusers', 'identity.employees', fn () => Uber::identity()->organization('org-1')->createUser([])],
            'identity users' => ['GET', '/v1/identity/organizations/org-1/orgusers', 'identity.employees', fn () => Uber::identity()->organization('org-1')->users()],
            'identity user' => ['GET', '/v1/identity/organizations/org-1/orguser/ou1', 'identity.employees', fn () => Uber::identity()->organization('org-1')->user('ou1')],
            'identity update user' => ['PUT', '/v1/identity/organizations/org-1/orguser/ou1', 'identity.employees', fn () => Uber::identity()->organization('org-1')->updateUser('ou1', [])],
            'identity delete user' => ['DELETE', '/v1/identity/organizations/org-1/orguser/ou1', 'identity.employees', fn () => Uber::identity()->organization('org-1')->deleteUser('ou1')],
            'identity assign role' => ['POST', '/v1/identity/organizations/org-1/roleassignments', 'identity.roleassignments', fn () => Uber::identity()->organization('org-1')->assignRole([])],
            'identity roles' => ['GET', '/v1/identity/organizations/org-1/roleassignments?orguser=ou1', 'identity.roleassignments', fn () => Uber::identity()->organization('org-1')->roleAssignments('ou1')],
            'identity remove role' => ['DELETE', '/v1/identity/organizations/org-1/orguser/ra1', 'identity.roleassignments', fn () => Uber::identity()->organization('org-1')->removeRoleAssignment('ra1')],

            // Drivers
            'drivers me' => ['GET', '/v1/partners/me', null, fn () => $user()->drivers()->me()],
            'drivers payments' => ['GET', '/v1/partners/payments?from_time=1&limit=50&offset=0', null, fn () => $user()->drivers()->payments(['from_time' => 1])],
            'drivers trips' => ['GET', '/v1/partners/trips?limit=20&offset=0', null, fn () => $user()->drivers()->trips([], 20)],

            // Vehicle suppliers
            'suppliers orgs' => ['GET', '/v1/vehicle-suppliers/orgs', 'vehicle_suppliers.organizations.read', fn () => Uber::vehicleSuppliers()->organizations()],
            'suppliers search drivers' => ['POST', '/v1/vehicle-suppliers/drivers/search', 'vehicle_suppliers.drivers.read', fn () => Uber::vehicleSuppliers()->searchDrivers(['email' => 'a@b.c'])],
            'suppliers drivers' => ['GET', '/v1/vehicle-suppliers/drivers?org_id=o1', 'solutions.suppliers.drivers.status.read', fn () => Uber::vehicleSuppliers()->drivers(['org_id' => 'o1'])],
            'suppliers driver actions' => ['GET', '/v1/vehicle-suppliers/drivers/actions', 'solutions.suppliers.drivers.status.read', fn () => Uber::vehicleSuppliers()->driverActions([])],
            'suppliers live location' => ['POST', '/v1/vehicle-suppliers/drivers/live-location', 'supplier.fleet.drivers.live_location.read', fn () => Uber::vehicleSuppliers()->liveLocations([])],
            'suppliers timeline' => ['POST', '/v1/vehicle-suppliers/driver/timeline-info', 'supplier.driver.activity.read', fn () => Uber::vehicleSuppliers()->driverTimeline([])],
            'suppliers partner' => ['GET', '/v1/partners/me', null, fn () => $user()->vehicleSuppliers()->partner()],
            'suppliers compliance' => ['GET', '/v1/vehicle-suppliers/partners/me/compliance', null, fn () => $user()->vehicleSuppliers()->partnerCompliance()],
            'suppliers risk' => ['GET', '/v3/vehicle-suppliers/partners/me/risk-profile?risk_models=a%2Cb', null, fn () => $user()->vehicleSuppliers()->partnerRiskProfile(['a', 'b'])],
            'suppliers cash block' => ['POST', '/v1/vehicle-suppliers/cash-block-action', 'vehicle_suppliers.cash_block.write', fn () => Uber::vehicleSuppliers()->cashBlock([])],
            'suppliers cash blocks' => ['POST', '/v1/vehicle-suppliers/query-cash-block-actions', 'vehicle_suppliers.cash_block.read', fn () => Uber::vehicleSuppliers()->cashBlockActions([])],
            'suppliers earner payments' => ['GET', '/v1/vehicle-suppliers/earners/payments', 'supplier.partner.payments', fn () => Uber::vehicleSuppliers()->earnerPayments()],
            'suppliers transactions' => ['POST', '/v1/vehicle-suppliers/transactions?org_id=o1', 'supplier.partner.payments', fn () => Uber::vehicleSuppliers()->transactions('o1')],
            'suppliers analytics' => ['POST', '/v1/vehicle-suppliers/analytics-data/query', 'solutions.suppliers.metrics.read', fn () => Uber::vehicleSuppliers()->analytics([])],
            'suppliers vehicle metrics' => ['GET', '/v2/vehicle-suppliers/vehicles', 'solutions.suppliers.metrics.read', fn () => Uber::vehicleSuppliers()->vehicleMetrics()],
            'suppliers create report' => ['POST', '/v1/vehicle-suppliers/suppliers/o1/reports', 'solutions.suppliers.reports', fn () => Uber::vehicleSuppliers()->createReport('o1', [])],
            'suppliers reports' => ['GET', '/v1/vehicle-suppliers/suppliers/o1/reports', 'solutions.suppliers.reports', fn () => Uber::vehicleSuppliers()->reports('o1')],
            'suppliers report' => ['GET', '/v1/vehicle-suppliers/suppliers/o1/reports/r1', 'solutions.suppliers.reports', fn () => Uber::vehicleSuppliers()->report('o1', 'r1')],
            'suppliers report link' => ['POST', '/v1/vehicle-suppliers/suppliers/o1/reports/r1/link', 'solutions.suppliers.reports', fn () => Uber::vehicleSuppliers()->reportLink('o1', 'r1')],
            'vehicles create' => ['POST', '/v1/vehicle-suppliers/vehicles', 'vehicle_suppliers.vehicles.write', fn () => Uber::vehicleSuppliers()->vehicles()->create([])],
            'vehicles list' => ['GET', '/v1/vehicle-suppliers/vehicles?org_id=o1', 'vehicle_suppliers.vehicles.read', fn () => Uber::vehicleSuppliers()->vehicles()->list(['org_id' => 'o1'])],
            'vehicles find' => ['GET', '/v1/vehicle-suppliers/vehicles/v1?fields=_all_', 'vehicle_suppliers.vehicles.read', fn () => Uber::vehicleSuppliers()->vehicles()->find('v1', true)],
            'vehicles update' => ['PATCH', '/v1/vehicle-suppliers/vehicles/v1', 'vehicle_suppliers.vehicles.write', fn () => Uber::vehicleSuppliers()->vehicles()->update('v1', [])],
            'vehicles remove' => ['DELETE', '/v1/vehicle-suppliers/vehicles/v1', 'vehicle_suppliers.vehicles.write', fn () => Uber::vehicleSuppliers()->vehicles()->remove('v1')],
            'vehicles search' => ['POST', '/v1/vehicle-suppliers/vehicles/search', 'vehicle_suppliers.vehicles.read', fn () => Uber::vehicleSuppliers()->vehicles()->search(['vin' => 'X'])],
            'vehicles transfer' => ['POST', '/v1/vehicle-suppliers/vehicles/v1/transfer', 'vehicle_suppliers.vehicles.write', fn () => Uber::vehicleSuppliers()->vehicles()->transfer('v1', 'o2')],
            'vehicles assign' => ['POST', '/v1/vehicle-suppliers/vehicles/v1/assign', 'vehicle_suppliers.vehicles.assignment', fn () => Uber::vehicleSuppliers()->vehicles()->assign('v1', 'd1')],
            'vehicles unassign' => ['POST', '/v1/vehicle-suppliers/vehicles/v1/unassign', 'vehicle_suppliers.vehicles.assignment', fn () => Uber::vehicleSuppliers()->vehicles()->unassign('v1', 'd1')],
            'vehicles document' => ['POST', '/v1/solutions/vehicles/v1/documents', 'vehicle_suppliers.vehicles.write', fn () => Uber::vehicleSuppliers()->vehicles()->uploadDocument('v1', 'INSURANCE', 'pdf', 'application/pdf')],
            'terms create' => ['POST', '/v1/vehicle-supplier/terms', 'vehicle_suppliers.terms.management', fn () => Uber::vehicleSuppliers()->terms()->create([])],
            'terms terminate' => ['PATCH', '/v1/vehicle-supplier/terms/t1/terminate', 'vehicle_suppliers.terms.management', fn () => Uber::vehicleSuppliers()->terms()->terminate('t1')],
            'terms adjust' => ['POST', '/v1/vehicle-supplier/terms/t1/adjust-balance', 'vehicle_suppliers.terms.adjustment vehicle_suppliers.terms.management', fn () => Uber::vehicleSuppliers()->terms()->adjustBalance('t1', [])],
            'financing create' => ['POST', '/v1/vehicle-supplier/financing/contracts', 'vehicle_suppliers.financing.contracts', fn () => Uber::vehicleSuppliers()->financing()->create([])],
            'financing find' => ['GET', '/v1/vehicle-supplier/financing/contracts/c1', 'vehicle_suppliers.financing.contracts', fn () => Uber::vehicleSuppliers()->financing()->find('c1')],
            'financing search' => ['POST', '/v1/vehicle-supplier/financing/contracts/search', 'vehicle_suppliers.financing.contracts', fn () => Uber::vehicleSuppliers()->financing()->search([])],
            'financing update' => ['PATCH', '/v1/vehicle-supplier/financing/contracts/update', 'vehicle_suppliers.financing.contracts', fn () => Uber::vehicleSuppliers()->financing()->update([])],
            'financing terminate' => ['PATCH', '/v1/vehicle-supplier/financing/contracts/terminate/c1', 'vehicle_suppliers.financing.contracts', fn () => Uber::vehicleSuppliers()->financing()->terminate('c1')],
            'financing adjust' => ['PATCH', '/v1/vehicle-supplier/financing/contracts/adjust', 'vehicle_suppliers.financing.contracts', fn () => Uber::vehicleSuppliers()->financing()->adjust([])],
            'shifts earner' => ['GET', '/v2/vehicle-supplier/shifts/earner/e1?start_time_utc=1000&end_time_utc=2000', 'vehicle_suppliers.shifts.management', fn () => Uber::vehicleSuppliers()->shifts()->forEarner('e1', 1000, 2000)],
            'shifts save' => ['POST', '/v2/vehicle-supplier/shifts/earner/e1', 'vehicle_suppliers.shifts.management', fn () => Uber::vehicleSuppliers()->shifts()->save('e1', 1000, 2000)],
            'shifts remove' => ['POST', '/v2/vehicle-supplier/shifts/delete/earner/e1', 'vehicle_suppliers.shifts.management', fn () => Uber::vehicleSuppliers()->shifts()->remove('e1', 1000, 2000)],
            'shifts driver' => ['GET', '/v1/vehicle-supplier/shifts/driver/d1?start_time_utc=1000&end_time_utc=2000', 'vehicle_suppliers.shifts.management', fn () => Uber::vehicleSuppliers()->shifts()->forDriver('d1', 1000, 2000)],
            'shifts save driver' => ['POST', '/v1/vehicle-supplier/shifts/driver/d1', 'vehicle_suppliers.shifts.management', fn () => Uber::vehicleSuppliers()->shifts()->saveForDriver('d1', [])],

            // Ads
            'ads accounts' => ['GET', '/v1/ads/ad-accounts', null, fn () => $user()->ads()->accounts()],
            'ads stores' => ['GET', '/v1/ads/acc1/stores', null, fn () => $user()->ads('acc1')->stores()],
            'ads campaigns' => ['GET', '/v1/ads/acc1/campaigns?campaign_id=c1%2Cc2', null, fn () => $user()->ads('acc1')->campaigns(['c1', 'c2'])],
            'ads create campaigns' => ['POST', '/v1/ads/acc1/campaigns', null, fn () => $user()->ads('acc1')->createCampaigns([])],
            'ads update campaigns' => ['PATCH', '/v1/ads/acc1/campaigns', null, fn () => $user()->ads('acc1')->updateCampaigns([])],
            'ads archive campaigns' => ['POST', '/v1/ads/acc1/campaigns/archive', null, fn () => $user()->ads('acc1')->archiveCampaigns(['c1'])],
            'ads ad groups' => ['GET', '/v1/ads/acc1/campaigns/c1/ad-groups', null, fn () => $user()->ads('acc1')->adGroups('c1')],
            'ads create ad groups' => ['POST', '/v1/ads/acc1/campaigns/c1/ad-groups', null, fn () => $user()->ads('acc1')->createAdGroups('c1', [])],
            'ads update ad groups' => ['PATCH', '/v1/ads/acc1/campaigns/c1/ad-groups', null, fn () => $user()->ads('acc1')->updateAdGroups('c1', [])],
            'ads ads' => ['GET', '/v1/ads/acc1/campaigns/c1/ad-groups/g1/ads', null, fn () => $user()->ads('acc1')->ads('c1', 'g1')],
            'ads create ads' => ['POST', '/v1/ads/acc1/campaigns/c1/ad-groups/g1/ads', null, fn () => $user()->ads('acc1')->createAds('c1', 'g1', [])],
            'ads update ads' => ['PATCH', '/v1/ads/acc1/campaigns/c1/ad-groups/g1/ads', null, fn () => $user()->ads('acc1')->updateAds('c1', 'g1', [])],
            'ads assets' => ['GET', '/v1/ads/acc1/assets', null, fn () => $user()->ads('acc1')->assets()],
            'ads create asset' => ['POST', '/v1/ads/acc1/assets', null, fn () => $user()->ads('acc1')->createAsset('logo', 'https://example.com/logo.png')],
            'ads creatives' => ['GET', '/v1/ads/acc1/creatives', null, fn () => $user()->ads('acc1')->creatives()],
            'ads create creatives' => ['POST', '/v1/ads/acc1/creatives', null, fn () => $user()->ads('acc1')->createCreatives([])],
            'ads products' => ['GET', '/v1/ads/acc1/products?page_limit=100', null, fn () => $user()->ads('acc1')->products(pageLimit: 100)],
            'ads create report' => ['POST', '/v1/ads/acc1/reporting/report', null, fn () => $user()->ads('acc1')->createReport([])],
            'ads report' => ['GET', '/v1/ads/acc1/reporting/r1', null, fn () => $user()->ads('acc1')->report('r1')],
            'ads sync report' => ['POST', '/v1/ads/acc1/reporting/sync', null, fn () => $user()->ads('acc1')->syncReport([])],

            // AI Solutions
            'ai submit batch' => ['POST', '/v1/scaledsolutions/batch', 'scaledsolutions.batch', fn () => Uber::aiSolutions()->submitBatch([])],
            'ai batch' => ['GET', '/v1/scaledsolutions/batch/b1', 'scaledsolutions.batch', fn () => Uber::aiSolutions()->batch('b1')],
            'ai batches' => ['POST', '/v1/scaledsolutions/project/p1/batches?limit=5', 'scaledsolutions.batch', fn () => Uber::aiSolutions()->batches('p1', 5)],
            'ai cancel batch' => ['POST', '/v1/scaledsolutions/batch/b1/cancel', 'scaledsolutions.batch', fn () => Uber::aiSolutions()->cancelBatch('b1')],
            'ai tasks' => ['GET', '/v1/scaledsolutions/batch/b1/tasks', 'scaledsolutions.batch', fn () => Uber::aiSolutions()->tasks('b1')],
            'ai generate result' => ['POST', '/v1/scaledsolutions/batch/b1/result/generate', 'scaledsolutions.batch', fn () => Uber::aiSolutions()->generateResult('b1')],
            'ai result' => ['GET', '/v1/scaledsolutions/batch/b1/result', 'scaledsolutions.batch', fn () => Uber::aiSolutions()->result('b1')],
            'ai translate' => ['POST', '/v1/scaledsolutions/localization/mt/translate', 'scaledsolutions.batch', fn () => Uber::aiSolutions()->translate('en_US', 'fr_FR', 'Hello')],
            'ai translate batch' => ['POST', '/v1/scaledsolutions/localization/mt/translate/batch', 'scaledsolutions.batch', fn () => Uber::aiSolutions()->translateBatch('en_US', 'fr_FR', ['Hello'])],
            'ai translate genai' => ['POST', '/v1/scaledsolutions/localization/genai/translate', 'scaledsolutions.batch', fn () => Uber::aiSolutions()->translateWithGenAi('en_US', 'fr_FR', 'Hello')],

            // Uber Pay
            'pay deposit' => ['GET', '/v1/payments/deposits/d1', 'payments.deposits', fn () => Uber::payments()->deposit('d1')],
            'pay update deposit' => ['POST', '/v1/payments/deposits/d1', 'payments.deposits', fn () => Uber::payments()->updateDeposit('d1', [])],
            'pay confirm deposit' => ['POST', '/v1/payments/deposits/d1/confirm', 'payments.deposits', fn () => Uber::payments()->confirmDeposit('d1')],
            'pay cancel deposit' => ['POST', '/v1/payments/deposits/d1/cancel', 'payments.deposits', fn () => Uber::payments()->cancelDeposit('d1', 'USER_CANCELLED')],
            'pay refund' => ['POST', '/v1/payments/refunds/r1', 'payments.deposits', fn () => Uber::payments()->updateRefund('r1', [])],
            'pay charge' => ['POST', '/v1/payments/charges/c1', 'payments.deposits', fn () => Uber::payments()->finalizeCharge('c1', [])],
            'pay payout' => ['POST', '/v1/payments/payouts/p1', 'payments.payouts', fn () => Uber::payments()->finalizePayout('p1', [])],
        ];
    }

    #[Test]
    #[DataProvider('endpoints')]
    public function it_calls_the_documented_endpoint(string $method, string $path, ?string $scope, Closure $call): void
    {
        $this->fake();

        $call();

        $requests = $this->apiRequests();
        $this->assertCount(1, $requests, 'Expected exactly one API request.');

        $request = $requests[0];
        $this->assertSame($method, $request->method());
        $this->assertSame(self::BASE.$path, $request->url());

        if ($scope === null) {
            $this->assertTrue($request->hasHeader('Authorization', 'Bearer user-token'), 'Expected the user token.');
            Http::assertNotSent(fn (Request $r) => $r->url() === self::TOKEN_URL);
        } else {
            $this->assertTrue($request->hasHeader('Authorization', 'Bearer app-token'), 'Expected an app token.');
            Http::assertSent(fn (Request $r) => $r->url() === self::TOKEN_URL && $r['scope'] === $scope);
        }
    }
}
