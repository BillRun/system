import React from 'react';
import { HashRouter, Routes, Route, Outlet } from 'react-router-dom';

import App, { Authentication, ErrorBoundary } from '@/components/App';
import { LoginPage, PageNotFound404, Welcome, About } from '@/components/StaticPages';
import { ChangePassword } from '@/components/UserForms';
import CustomersList from '@/components/CustomersList';
import CustomerSetup from '@/components/CustomerSetup';
import ProductsList from '@/components/ProductsList';
import Product from '@/components/Product';
import ReportsList from '@/components/ReportsList';
import Report from '@/components/Report';
import PlansList from '@/components/PlansList';
import Plan from '@/components/Plan';
import DiscountsList from '@/components/DiscountsList';
import Discount from '@/components/Discount';
import ChargesList from '@/components/ChargesList';
import Charge from '@/components/Charge';
import ServicesList from '@/components/ServicesList';
import Service from '@/components/Service';
import InputProcessorsList from '@/components/InputProcessorsList';
import { ExportGenerator, ExportGeneratorsList } from '@/components/ExportGenerator';
import InputProcessor from '@/components/InputProcessor';
import UsageList from '@/components/UsageList';
import RunCycle from '@/components/Cycle';
import QueueList from '@/components/QueueList';
import InvoicesList from '@/components/InvoicesList';
import Settings from '@/components/Settings';
import PaymentGateways from '@/components/PaymentGateways';
import UserList from '@/components/UserList';
import UserSetup from '@/components/UserSetup';
import SelectTemplate from '@/components/InputProcessor/SelectTemplate';
import Collections from '@/components/Collections';
import InvoiceTemplate from '@/components/InvoiceTemplate';
import EmailTemplates from '@/components/EmailTemplates';
import PrepaidPlansList from '@/components/PrepaidPlansList';
import PrepaidPlan from '@/components/PrepaidPlan';
import AuditTrail from '@/components/AuditTrail';
import PrepaidIncludesList from '@/components/PrepaidIncludesList';
import PrepaidIncludeSetup from '@/components/PrepaidInclude';
import ChargingPlansList from '@/components/ChargingPlansList';
import ChargingPlanSetup from '@/components/ChargingPlan';
import AutoRenewsList from '@/components/AutoRenew/AutoRenewsList';
import AutoRenewSetup from '@/components/AutoRenew/AutoRenewSetup';
import CustomFields from '@/components/CustomFields';
import Events from '@/components/Events';
import RequestPaymentFiles from '@/components/PaymentFiles/RequestPaymentFiles';
import ResponsePaymentFiles from '@/components/PaymentFiles/ResponsePaymentFiles';
import PaymentsFiles from '@/components/PaymentFiles/PaymentsFiles';
import ChargeList from '@/components/Charging';
import { ImporterSetup } from '../components/Importer';
import { ExporterSetup } from '../components/Exporter';
import { ImmediateInvoiceSetup } from '../components/ImmediateInvoice';
import { RefundInvoiceSetup } from '../components/RefundInvoice';
import SuggestionsSetup, { SuggestionsList } from '../components/Suggestions';
import { TaxList, TaxSetup, TaxMapping } from '@/components/Tax';

// Pre-compute Authentication-wrapped components (required because Authentication()
// returns a component, and JSX can't call functions inside angle brackets).
const AuthWelcome             = Authentication(Welcome);
const AuthUserList            = Authentication(UserList);
const AuthUserSetup           = Authentication(UserSetup);
const AuthPlansList           = Authentication(PlansList);
const AuthPlan                = Authentication(Plan);
const AuthServicesList        = Authentication(ServicesList);
const AuthService             = Authentication(Service);
const AuthDiscountsList       = Authentication(DiscountsList);
const AuthDiscount            = Authentication(Discount);
const AuthChargesList         = Authentication(ChargesList);
const AuthCharge              = Authentication(Charge);
const AuthProductsList        = Authentication(ProductsList);
const AuthProduct             = Authentication(Product);
const AuthPrepaidPlansList    = Authentication(PrepaidPlansList);
const AuthPrepaidPlan         = Authentication(PrepaidPlan);
const AuthChargingPlansList   = Authentication(ChargingPlansList);
const AuthChargingPlanSetup   = Authentication(ChargingPlanSetup);
const AuthAutoRenewsList      = Authentication(AutoRenewsList);
const AuthAutoRenewSetup      = Authentication(AutoRenewSetup);
const AuthPrepaidIncludesList = Authentication(PrepaidIncludesList);
const AuthPrepaidIncludeSetup = Authentication(PrepaidIncludeSetup);
const AuthCustomersList       = Authentication(CustomersList);
const AuthCustomerSetup       = Authentication(CustomerSetup);
const AuthTaxList             = Authentication(TaxList);
const AuthTaxSetup            = Authentication(TaxSetup);
const AuthTaxMapping          = Authentication(TaxMapping);
const AuthReportsList         = Authentication(ReportsList);
const AuthReport              = Authentication(Report);
const AuthInputProcessor      = Authentication(InputProcessor);
const AuthInputProcessorsList = Authentication(InputProcessorsList);
const AuthExportGenerator     = Authentication(ExportGenerator);
const AuthExportGeneratorsList= Authentication(ExportGeneratorsList);
const AuthUsageList           = Authentication(UsageList);
const AuthRunCycle            = Authentication(RunCycle);
const AuthQueueList           = Authentication(QueueList);
const AuthInvoicesList        = Authentication(InvoicesList);
const AuthPaymentsFiles       = Authentication(PaymentsFiles);
const AuthChargeList          = Authentication(ChargeList);
const AuthRequestPaymentFiles = Authentication(RequestPaymentFiles);
const AuthResponsePaymentFiles= Authentication(ResponsePaymentFiles);
const AuthSettings            = Authentication(Settings);
const AuthPaymentGateways     = Authentication(PaymentGateways);
const AuthSelectTemplate      = Authentication(SelectTemplate);
const AuthCollections         = Authentication(Collections);
const AuthInvoiceTemplate     = Authentication(InvoiceTemplate);
const AuthAuditTrail          = Authentication(AuditTrail);
const AuthCustomFields        = Authentication(CustomFields);
const AuthEvents              = Authentication(Events);
const AuthEmailTemplates      = Authentication(EmailTemplates);
const AuthImporterSetup       = Authentication(ImporterSetup);
const AuthExporterSetup       = Authentication(ExporterSetup);
const AuthImmediateInvoice    = Authentication(ImmediateInvoiceSetup);
const AuthRefundInvoice       = Authentication(RefundInvoiceSetup);
const AuthSuggestionsSetup    = Authentication(SuggestionsSetup);
const AuthSuggestionsList     = Authentication(SuggestionsList);

// App shell: renders the layout + <Outlet /> for nested routes
const AppShell = () => <App><Outlet /></App>;

const AppRoutes = () => (
  <ErrorBoundary>
    <HashRouter>
      <Routes>
        <Route path="/" element={<AppShell />}>
          {/* Default / root */}
          <Route index element={<AuthWelcome />} />

          {/* Users */}
          <Route path="users" element={<AuthUserList />} />
          <Route path="users/user/:itemId" element={<AuthUserSetup />} />
          <Route path="users/user" element={<AuthUserSetup />} />

          {/* Plans */}
          <Route path="plans" element={<AuthPlansList />} />
          <Route path="plans/plan/:itemId" element={<AuthPlan />} />
          <Route path="plans/plan" element={<AuthPlan />} />

          {/* Services */}
          <Route path="services" element={<AuthServicesList />} />
          <Route path="services/service/:itemId" element={<AuthService />} />
          <Route path="services/service" element={<AuthService />} />

          {/* Discounts */}
          <Route path="discounts" element={<AuthDiscountsList />} />
          <Route path="discounts/discount/:itemId" element={<AuthDiscount />} />
          <Route path="discounts/discount" element={<AuthDiscount />} />

          {/* Charges */}
          <Route path="charges" element={<AuthChargesList />} />
          <Route path="charges/charge/:itemId" element={<AuthCharge />} />
          <Route path="charges/charge" element={<AuthCharge />} />

          {/* Products */}
          <Route path="products" element={<AuthProductsList />} />
          <Route path="products/product/:itemId" element={<AuthProduct />} />
          <Route path="products/product" element={<AuthProduct />} />

          {/* Prepaid Plans */}
          <Route path="prepaid_plans" element={<AuthPrepaidPlansList />} />
          <Route path="prepaid_plans/prepaid_plan/:itemId" element={<AuthPrepaidPlan />} />
          <Route path="prepaid_plans/prepaid_plan" element={<AuthPrepaidPlan />} />

          {/* Charging Plans */}
          <Route path="charging_plans" element={<AuthChargingPlansList />} />
          <Route path="charging_plans/charging_plan/:itemId" element={<AuthChargingPlanSetup />} />
          <Route path="charging_plans/charging_plan" element={<AuthChargingPlanSetup />} />

          {/* Auto Renews */}
          <Route path="auto_renews" element={<AuthAutoRenewsList />} />
          <Route path="auto_renews/auto_renew/:itemId" element={<AuthAutoRenewSetup />} />
          <Route path="auto_renews/auto_renew" element={<AuthAutoRenewSetup />} />

          {/* Prepaid Includes */}
          <Route path="prepaid_includes" element={<AuthPrepaidIncludesList />} />
          <Route path="prepaid_includes/prepaid_include/:itemId" element={<AuthPrepaidIncludeSetup />} />
          <Route path="prepaid_includes/prepaid_include" element={<AuthPrepaidIncludeSetup />} />

          {/* Customers */}
          <Route path="customers" element={<AuthCustomersList />} />
          <Route path="customers/customer/:itemId" element={<AuthCustomerSetup />} />
          <Route path="customers/customer" element={<AuthCustomerSetup />} />

          {/* Taxes */}
          <Route path="taxes" element={<AuthTaxList />} />
          <Route path="taxes/tax/:itemId" element={<AuthTaxSetup />} />
          <Route path="taxes/tax" element={<AuthTaxSetup />} />
          <Route path="taxes/mapping-rules" element={<AuthTaxMapping />} />

          {/* Reports */}
          <Route path="reports" element={<AuthReportsList />} />
          <Route path="reports/report/:itemId" element={<AuthReport />} />
          <Route path="reports/report" element={<AuthReport />} />

          {/* Flat routes */}
          <Route path="input_processor" element={<AuthInputProcessor />} />
          <Route path="input_processors" element={<AuthInputProcessorsList />} />
          <Route path="export_generator/:name" element={<AuthExportGenerator />} />
          <Route path="export_generator" element={<AuthExportGenerator />} />
          <Route path="export_generators" element={<AuthExportGeneratorsList />} />
          <Route path="usage" element={<AuthUsageList />} />
          <Route path="run_cycle" element={<AuthRunCycle />} />
          <Route path="queue" element={<AuthQueueList />} />
          <Route path="invoices" element={<AuthInvoicesList />} />
          <Route path="payments" element={<AuthPaymentsFiles />} />
          <Route path="charging" element={<AuthChargeList />} />
          <Route path="payment-files" element={<AuthRequestPaymentFiles />} />
          <Route path="response-payment-files" element={<AuthResponsePaymentFiles />} />
          <Route path="settings" element={<AuthSettings />} />
          <Route path="payment_gateways" element={<AuthPaymentGateways />} />
          <Route path="select_input_processor_template" element={<AuthSelectTemplate />} />
          <Route path="collections" element={<AuthCollections />} />
          <Route path="invoice-template" element={<AuthInvoiceTemplate />} />
          <Route path="audit-trail" element={<AuthAuditTrail />} />
          <Route path="custom_fields" element={<AuthCustomFields />} />
          <Route path="events" element={<AuthEvents />} />
          <Route path="email_templates" element={<AuthEmailTemplates />} />

          {/* Public routes (no auth) */}
          <Route path="login" element={<LoginPage />} />
          <Route path="about" element={<About />} />
          <Route path="changepassword/:itemId" element={<ChangePassword />} />
          <Route path="changepassword" element={<ChangePassword />} />

          {/* Import / Export */}
          <Route path="import/:itemType" element={<AuthImporterSetup />} />
          <Route path="import" element={<AuthImporterSetup />} />
          <Route path="export/:itemType" element={<AuthExporterSetup />} />
          <Route path="export" element={<AuthExporterSetup />} />

          {/* Invoices */}
          <Route path="immediate-invoice-charge" element={<AuthImmediateInvoice />} />
          <Route path="immediate-invoice-refund" element={<AuthRefundInvoice />} />

          {/* Suggestions */}
          <Route path="suggestions" element={<AuthSuggestionsSetup />} />
          <Route path="suggestions/:itemId" element={<AuthSuggestionsList />} />

          {/* 404 */}
          <Route path="*" element={<PageNotFound404 />} />
        </Route>
      </Routes>
    </HashRouter>
  </ErrorBoundary>
);

export default AppRoutes;
