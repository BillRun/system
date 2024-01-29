import { apiBillRun, apiBillRunErrorHandler, apiBillRunSuccessHandler } from '@/common/Api';
import { generateOneTimeInvoiceQuery } from '@/common/ApiQueries';

export const generateOneTimeInvoice = (aid, lines, sendMail = false) => (dispatch) => {
  const query = generateOneTimeInvoiceQuery(aid, lines, sendMail);
  return apiBillRun(query)
    .then(success => dispatch(apiBillRunSuccessHandler(success, 'Immediate invoice successfully generated')))
    .catch(error => dispatch(apiBillRunErrorHandler(error, 'Error generating the invoice')))
}
