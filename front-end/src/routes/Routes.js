import React from 'react';

import { Router, hashHistory, Route, IndexRoute } from 'react-router';

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
import { ImporterSetup } from '../components/Importer';
import { ExporterSetup } from '../components/Exporter';
import { ImmediateInvoiceSetup } from '../components/ImmediateInvoice';
import SuggestionsSetup, { SuggestionsList } from '../components/Suggestions';
import { TaxList, TaxSetup, TaxMapping } from '@/components/Tax';


const routes = () => (
  <ErrorBoundary>
    <Router history={hashHistory}>
      <Route path="/" component={App}>
        <IndexRoute component={Authentication(Welcome)} title="" />

        <Route path="users" >
          <IndexRoute component={Authentication(UserList)} title="Users" />
          <Route path="user/:itemId" component={Authentication(UserSetup)} />
          <Route path="user" component={Authentication(UserSetup)} />
        </Route>

        <Route path="plans">
          <IndexRoute component={Authentication(PlansList)} title="Plans" />
          <Route path="plan/:itemId" component={Authentication(Plan)} />
          <Route path="plan" component={Authentication(Plan)} />
        </Route>

        <Route path="services" >
          <IndexRoute component={Authentication(ServicesList)} title="Services" />
          <Route path="service/:itemId" component={Authentication(Service)} />
          <Route path="service" component={Authentication(Service)} />
        </Route>

        <Route path="discounts" >
          <IndexRoute component={Authentication(DiscountsList)} title="Discounts" />
          <Route path="discount/:itemId" component={Authentication(Discount)} />
          <Route path="discount" component={Authentication(Discount)} />
        </Route>

        <Route path="charges" >
          <IndexRoute component={Authentication(ChargesList)} title="Conditional Charges" />
          <Route path="charge/:itemId" component={Authentication(Charge)} />
          <Route path="charge" component={Authentication(Charge)} />
        </Route>

        <Route path="products" >
          <IndexRoute component={Authentication(ProductsList)} title="Products" />
          <Route path="product/:itemId" component={Authentication(Product)} />
          <Route path="product" component={Authentication(Product)} />
        </Route>

        <Route path="prepaid_plans" >
          <IndexRoute component={Authentication(PrepaidPlansList)} title="Prepaid Plans" />
          <Route path="prepaid_plan/:itemId" component={Authentication(PrepaidPlan)} />
          <Route path="prepaid_plan" component={Authentication(PrepaidPlan)} />
        </Route>

        <Route path="charging_plans" >
          <IndexRoute component={Authentication(ChargingPlansList)} title="Buckets Groups" />
          <Route path="charging_plan/:itemId" component={Authentication(ChargingPlanSetup)} />
          <Route path="charging_plan" component={Authentication(ChargingPlanSetup)} />
        </Route>

        <Route path="auto_renews" >
          <IndexRoute component={Authentication(AutoRenewsList)} title="Recurring Charges" />
          <Route path="auto_renew/:itemId" component={Authentication(AutoRenewSetup)} />
          <Route path="auto_renew" component={Authentication(AutoRenewSetup)} />
        </Route>

        <Route path="prepaid_includes" >
          <IndexRoute component={Authentication(PrepaidIncludesList)} title="Prepaid Buckets" />
          <Route path="prepaid_include/:itemId" component={Authentication(PrepaidIncludeSetup)} />
          <Route path="prepaid_include" component={Authentication(PrepaidIncludeSetup)} />
        </Route>

        <Route path="customers" >
          <IndexRoute component={Authentication(CustomersList)} title="Customers" />
          <Route path="customer/:itemId" component={Authentication(CustomerSetup)} />
          <Route path="customer" component={Authentication(CustomerSetup)} />
        </Route>

        <Route path="taxes" >
          <IndexRoute component={Authentication(TaxList)} title="Tax Rates" />
          <Route path="tax/:itemId" component={Authentication(TaxSetup)} />
          <Route path="tax" component={Authentication(TaxSetup)} />
          <Route path="tax" component={Authentication(TaxSetup)} />
          <Route path="mapping-rules" component={Authentication(TaxMapping)} />
        </Route>

        <Route path="reports" >
          <IndexRoute component={Authentication(ReportsList)} title="Reports" />
          <Route path="report/:itemId" component={Authentication(Report)} />
          <Route path="report" component={Authentication(Report)} />
        </Route>

        <Route path="/input_processor" component={Authentication(InputProcessor)} />
        <Route path="/input_processors" component={Authentication(InputProcessorsList)} title="Input Processors" />
        <Route path="/export_generator(/:name)" component={Authentication(ExportGenerator)} title="Export Generator" />
        <Route path="/export_generators" component={Authentication(ExportGeneratorsList)} title="Export Generators" />
        <Route path="/usage" component={Authentication(UsageList)} title="Usage" />
        <Route path="/run_cycle" component={Authentication(RunCycle)} title="Billing Cycle" />
        <Route path="/queue" component={Authentication(QueueList)} title="Queue" />
        <Route path="/invoices" component={Authentication(InvoicesList)} title="Invoices" />
        <Route path="/payments" component={Authentication(PaymentsFiles)} title="Payments" />
        <Route path="/custom-payment-files" component={Authentication(RequestPaymentFiles)} title="Custom Transactions Request File" />
        <Route path="/response-custom-payment-files" component={Authentication(ResponsePaymentFiles)} title="Custom Transactions Response File"/>
        <Route path="/settings" component={Authentication(Settings)} title="General Settings" />
        <Route path="/payment_gateways" component={Authentication(PaymentGateways)} title="Payment Gateways" />
        <Route path="/select_input_processor_template" component={Authentication(SelectTemplate)} title="Create New Input Processor" />
        <Route path="/collections" component={Authentication(Collections)} title="Collection" />
        <Route path="/invoice-template" component={Authentication(InvoiceTemplate)} title="Invoice Template" />
        <Route path="/audit-trail" component={Authentication(AuditTrail)} title="Audit Trail" />
        <Route path="/custom_fields" component={Authentication(CustomFields)} title="Custom Fields" />
        <Route path="/events" component={Authentication(Events)} title="Events" />
        <Route path="/email_templates" component={Authentication(EmailTemplates)} title="Email Templates" />

        <Route path="/login" component={LoginPage} title="Login" />
        <Route path="/about" component={About} title="About" />
        <Route path="/changepassword(/:itemId)" component={ChangePassword} title="Change Password" />
        <Route path="/import(/:itemType)" component={Authentication(ImporterSetup)} />
        <Route path="/export(/:itemType)" component={Authentication(ExporterSetup)} />
        <Route path="/immediate-invoice" component={Authentication(ImmediateInvoiceSetup)} title="Create immediate invoices" />
        <Route path="suggestions" >
          <IndexRoute component={Authentication(SuggestionsSetup)} title="Repricing Suggestions" />
          <Route path=":itemId" component={Authentication(SuggestionsList)} title="Customer Repricing Suggestions" />
        </Route>
        <Route path="*" component={PageNotFound404} title=" " />
      </Route>
    </Router>
  </ErrorBoundary>
);

export default routes;
