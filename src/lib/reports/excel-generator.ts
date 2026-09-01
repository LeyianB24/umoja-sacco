import ExcelJS from 'exceljs';

const FOREST_HEADER_FILL: ExcelJS.Fill = {
  type: 'pattern',
  pattern: 'solid',
  fgColor: { argb: 'FF0B2419' }, // Forest brand color
};

const HEADER_FONT: Partial<ExcelJS.Font> = {
  name: 'Calibri',
  size: 11,
  bold: true,
  color: { argb: 'FFFFFFFF' },
};

function styleHeaderRow(row: ExcelJS.Row) {
  row.eachCell((cell) => {
    cell.fill = FOREST_HEADER_FILL;
    cell.font = HEADER_FONT;
    cell.alignment = { vertical: 'middle', horizontal: 'center' };
  });
  row.height = 24;
}

function autoFitColumns(worksheet: ExcelJS.Worksheet) {
  worksheet.columns.forEach((col) => {
    let maxLength = 14;
    col.eachCell?.({ includeEmpty: true }, (cell) => {
      const v = cell.value ? cell.value.toString() : '';
      if (v.length > maxLength) maxLength = Math.min(v.length + 3, 40);
    });
    col.width = maxLength;
  });
}

/**
 * Generate Loan Statement Excel Workbook
 */
export async function generateLoanStatementExcel(loan: any, repayments: any[] = []): Promise<Buffer> {
  const wb = new ExcelJS.Workbook();
  wb.creator = 'Umoja SACCO Management System';
  wb.created = new Date();

  // Sheet 1: Loan Overview
  const wsDetails = wb.addWorksheet('Loan Overview');
  wsDetails.views = [{ showGridLines: true }];

  wsDetails.addRow(['UMOJA DRIVERS SACCO - LOAN STATEMENT']);
  wsDetails.getRow(1).font = { size: 14, bold: true, color: { argb: 'FF0B2419' } };
  wsDetails.addRow([`Generated: ${new Date().toLocaleDateString('en-GB')}`]);
  wsDetails.addRow([]);

  wsDetails.addRow(['Metric', 'Details']);
  styleHeaderRow(wsDetails.getRow(4));

  wsDetails.addRow(['Loan Reference', loan.reference_no || `LN-${loan.loan_id}`]);
  wsDetails.addRow(['Principal Amount (KES)', Number(loan.amount)]);
  wsDetails.addRow(['Interest Rate', `${loan.interest_rate || 12}% p.a.`]);
  wsDetails.addRow(['Tenure (Months)', loan.duration_months || 12]);
  wsDetails.addRow(['Total Payable (KES)', Number(loan.total_payable || loan.amount)]);
  wsDetails.addRow(['Current Outstanding Balance (KES)', Number(loan.current_balance || 0)]);
  wsDetails.addRow(['Loan Status', loan.status]);
  wsDetails.addRow(['Application Date', loan.application_date ? new Date(loan.application_date).toLocaleDateString('en-GB') : '—']);

  autoFitColumns(wsDetails);

  // Sheet 2: Repayment Schedule & History
  const wsRepayments = wb.addWorksheet('Repayment History');
  wsRepayments.views = [{ state: 'frozen', ySplit: 1, showGridLines: true }];

  wsRepayments.addRow(['Payment Date', 'Reference', 'Payment Method', 'Amount Paid (KES)', 'Remaining Balance (KES)']);
  styleHeaderRow(wsRepayments.getRow(1));

  let startRow = 2;
  repayments.forEach((r) => {
    wsRepayments.addRow([
      r.payment_date ? new Date(r.payment_date).toLocaleDateString('en-GB') : '—',
      r.reference_no || 'MPESA',
      r.payment_method || 'M-Pesa',
      Number(r.amount_paid || r.amount || 0),
      Number(r.remaining_balance || 0),
    ]);
  });

  const endRow = Math.max(2, repayments.length + 1);
  if (repayments.length > 0) {
    const totalRow = wsRepayments.addRow(['TOTAL PAID', '', '', { formula: `SUM(D${startRow}:D${endRow})` }, '']);
    totalRow.font = { bold: true };
  }

  autoFitColumns(wsRepayments);

  const arrayBuffer = await wb.xlsx.writeBuffer();
  return Buffer.from(arrayBuffer);
}

/**
 * Generate Member Account Statement Excel Workbook
 */
export async function generateAccountStatementExcel(member: any, transactions: any[] = []): Promise<Buffer> {
  const wb = new ExcelJS.Workbook();
  wb.creator = 'Umoja SACCO Management System';
  wb.created = new Date();

  const ws = wb.addWorksheet('Statement of Account');
  ws.views = [{ state: 'frozen', ySplit: 5, showGridLines: true }];

  ws.addRow(['UMOJA DRIVERS SACCO - OFFICIAL MEMBER STATEMENT']);
  ws.getRow(1).font = { size: 14, bold: true, color: { argb: 'FF0B2419' } };
  ws.addRow([`Member: ${member.full_name} (${member.member_reg_no}) | ID: ${member.national_id || 'N/A'}`]);
  ws.addRow([`Generated on: ${new Date().toLocaleDateString('en-GB')} | Currency: KES`]);
  ws.addRow([]);

  ws.addRow(['Date', 'Reference No', 'Transaction Type', 'Category', 'Description', 'Amount (KES)', 'Status']);
  styleHeaderRow(ws.getRow(5));

  let rIdx = 6;
  transactions.forEach((tx) => {
    ws.addRow([
      tx.transaction_date ? new Date(tx.transaction_date).toLocaleDateString('en-GB') : '—',
      tx.reference_no || '—',
      tx.transaction_type || 'General',
      tx.category || 'General',
      tx.description || '—',
      Number(tx.amount || 0),
      tx.type === 'debit' ? 'Debit (-)' : 'Credit (+)',
    ]);
    rIdx++;
  });

  if (transactions.length > 0) {
    const sumRow = ws.addRow(['TOTAL TRANSACTIONS', '', '', '', '', { formula: `SUM(F6:F${rIdx - 1})` }, '']);
    sumRow.font = { bold: true };
  }

  autoFitColumns(ws);

  const arrayBuffer = await wb.xlsx.writeBuffer();
  return Buffer.from(arrayBuffer);
}

/**
 * Generate Daily Income History Excel
 */
export async function generateIncomeHistoryExcel(incomeList: any[] = []): Promise<Buffer> {
  const wb = new ExcelJS.Workbook();
  wb.creator = 'Umoja SACCO Management System';

  const ws = wb.addWorksheet('Daily Income Log');
  ws.views = [{ state: 'frozen', ySplit: 1, showGridLines: true }];

  ws.addRow(['Date', 'Revenue Source', 'Gross Revenue (KES)', '10% Auto Savings (KES)', '2% Contribution (KES)', 'Notes']);
  styleHeaderRow(ws.getRow(1));

  let rIdx = 2;
  incomeList.forEach((inc) => {
    const amt = Number(inc.amount || 0);
    ws.addRow([
      inc.date ? new Date(inc.date).toLocaleDateString('en-GB') : '—',
      (inc.source || '').replace(/_/g, ' '),
      amt,
      Math.round(amt * 0.1 * 100) / 100,
      Math.max(50, Math.round(amt * 0.02 * 100) / 100),
      inc.notes || '',
    ]);
    rIdx++;
  });

  if (incomeList.length > 0) {
    const sumRow = ws.addRow([
      'TOTALS',
      '',
      { formula: `SUM(C2:C${rIdx - 1})` },
      { formula: `SUM(D2:D${rIdx - 1})` },
      { formula: `SUM(E2:E${rIdx - 1})` },
      '',
    ]);
    sumRow.font = { bold: true };
  }

  autoFitColumns(ws);

  const arrayBuffer = await wb.xlsx.writeBuffer();
  return Buffer.from(arrayBuffer);
}

/**
 * Generate Member Investment Portfolio Excel
 */
export async function generatePortfolioExcel(investments: any[] = []): Promise<Buffer> {
  const wb = new ExcelJS.Workbook();
  wb.creator = 'Umoja SACCO';

  const ws = wb.addWorksheet('Investment Portfolio');
  ws.views = [{ state: 'frozen', ySplit: 1, showGridLines: true }];

  ws.addRow(['Asset Name', 'Asset Class', 'Purchase Date', 'Units', 'Cost Basis (KES)', 'Current Value (KES)', 'Return (%)', 'Notes']);
  styleHeaderRow(ws.getRow(1));

  let rIdx = 2;
  investments.forEach((inv) => {
    const cost = Number(inv.cost_price || 0);
    const curr = Number(inv.current_value || cost);
    const ret = cost > 0 ? Math.round(((curr - cost) / cost) * 10000) / 100 : 0;

    ws.addRow([
      inv.asset_name,
      inv.type?.replace(/_/g, ' '),
      inv.purchase_date ? new Date(inv.purchase_date).toLocaleDateString('en-GB') : '—',
      Number(inv.quantity || 1),
      cost,
      curr,
      `${ret}%`,
      inv.notes || '',
    ]);
    rIdx++;
  });

  if (investments.length > 0) {
    const sumRow = ws.addRow([
      'PORTFOLIO TOTALS',
      '',
      '',
      '',
      { formula: `SUM(E2:E${rIdx - 1})` },
      { formula: `SUM(F2:F${rIdx - 1})` },
      '',
      '',
    ]);
    sumRow.font = { bold: true };
  }

  autoFitColumns(ws);

  const arrayBuffer = await wb.xlsx.writeBuffer();
  return Buffer.from(arrayBuffer);
}

/**
 * Generate Statement of Financial Position (Balance Sheet) Excel
 */
export async function generateBalanceSheetExcel(data: {
  assets: { account_name: string; category: string; balance: number }[];
  liabilities: { account_name: string; category: string; balance: number }[];
  equity: { account_name: string; category: string; balance: number }[];
  totalAssets: number;
  totalLiabilities: number;
  totalEquity: number;
  dateStr?: string;
}): Promise<Buffer> {
  const wb = new ExcelJS.Workbook();
  wb.creator = 'Umoja SACCO Management System';
  const ws = wb.addWorksheet('Balance Sheet');
  ws.views = [{ showGridLines: true }];

  ws.addRow(['UMOJA DRIVERS CO-OPERATIVE SAVINGS & CREDIT SOCIETY LTD']);
  ws.getRow(1).font = { size: 14, bold: true, color: { argb: 'FF0B2419' } };
  ws.addRow(['STATEMENT OF FINANCIAL POSITION (BALANCE SHEET)']);
  ws.getRow(2).font = { size: 12, bold: true, color: { argb: 'FF374151' } };
  ws.addRow([`As of: ${data.dateStr || new Date().toLocaleDateString('en-GB')} | Currency: KES (Kenya Shillings)`]);
  ws.addRow([]);

  ws.addRow(['Category / Account Name', 'Notes', 'Amount (KES)']);
  styleHeaderRow(ws.getRow(5));

  // Assets
  const aHeader = ws.addRow(['1. ASSETS', '', '']);
  aHeader.font = { bold: true, color: { argb: 'FF0B2419' } };
  data.assets.forEach((a) => {
    ws.addRow([`   ${a.account_name}`, a.category, Number(a.balance)]);
  });
  const aTotal = ws.addRow(['TOTAL ASSETS', '', data.totalAssets]);
  aTotal.font = { bold: true };

  ws.addRow([]);

  // Liabilities
  const lHeader = ws.addRow(['2. LIABILITIES', '', '']);
  lHeader.font = { bold: true, color: { argb: 'FF0B2419' } };
  data.liabilities.forEach((l) => {
    ws.addRow([`   ${l.account_name}`, l.category, Number(l.balance)]);
  });
  const lTotal = ws.addRow(['TOTAL LIABILITIES', '', data.totalLiabilities]);
  lTotal.font = { bold: true };

  ws.addRow([]);

  // Equity
  const eHeader = ws.addRow(['3. MEMBERS EQUITY & RESERVES', '', '']);
  eHeader.font = { bold: true, color: { argb: 'FF0B2419' } };
  data.equity.forEach((e) => {
    ws.addRow([`   ${e.account_name}`, e.category, Number(e.balance)]);
  });
  const eTotal = ws.addRow(['TOTAL EQUITY & RESERVES', '', data.totalEquity]);
  eTotal.font = { bold: true };

  ws.addRow([]);
  const netCheck = ws.addRow(['TOTAL LIABILITIES & EQUITY', '', data.totalLiabilities + data.totalEquity]);
  netCheck.font = { bold: true, size: 11, color: { argb: 'FF0B2419' } };

  autoFitColumns(ws);

  const arrayBuffer = await wb.xlsx.writeBuffer();
  return Buffer.from(arrayBuffer);
}

/**
 * Generate Comprehensive Income Statement (P&L) Excel
 */
export async function generateIncomeStatementExcel(data: {
  revenues: { title: string; category: string; amount: number }[];
  expenses: { title: string; category: string; amount: number }[];
  totalRevenue: number;
  totalExpenses: number;
  netSurplus: number;
  periodStr?: string;
}): Promise<Buffer> {
  const wb = new ExcelJS.Workbook();
  wb.creator = 'Umoja SACCO Management System';
  const ws = wb.addWorksheet('Income Statement');
  ws.views = [{ showGridLines: true }];

  ws.addRow(['UMOJA DRIVERS CO-OPERATIVE SAVINGS & CREDIT SOCIETY LTD']);
  ws.getRow(1).font = { size: 14, bold: true, color: { argb: 'FF0B2419' } };
  ws.addRow(['STATEMENT OF COMPREHENSIVE INCOME (PROFIT & LOSS)']);
  ws.getRow(2).font = { size: 12, bold: true, color: { argb: 'FF374151' } };
  ws.addRow([`Period: ${data.periodStr || 'Year-To-Date (2026)'} | Currency: KES`]);
  ws.addRow([]);

  ws.addRow(['Line Item / Description', 'Classification', 'Amount (KES)']);
  styleHeaderRow(ws.getRow(5));

  // Operating Revenue
  const revHeader = ws.addRow(['OPERATING INCOME & REVENUES', '', '']);
  revHeader.font = { bold: true, color: { argb: 'FF0B2419' } };
  data.revenues.forEach((r) => {
    ws.addRow([`   ${r.title}`, r.category, Number(r.amount)]);
  });
  const revTotal = ws.addRow(['TOTAL OPERATING REVENUE', '', data.totalRevenue]);
  revTotal.font = { bold: true };

  ws.addRow([]);

  // Operating Expenses
  const expHeader = ws.addRow(['OPERATING EXPENDITURES & PROVISIONS', '', '']);
  expHeader.font = { bold: true, color: { argb: 'FF0B2419' } };
  data.expenses.forEach((e) => {
    ws.addRow([`   ${e.title}`, e.category, Number(e.amount)]);
  });
  const expTotal = ws.addRow(['TOTAL OPERATING EXPENDITURES', '', data.totalExpenses]);
  expTotal.font = { bold: true };

  ws.addRow([]);

  // Net Surplus
  const surplusRow = ws.addRow(['NET OPERATING SURPLUS BEFORE TAX & DIVIDENDS', '', data.netSurplus]);
  surplusRow.font = { bold: true, size: 12, color: { argb: 'FF0B2419' } };

  autoFitColumns(ws);

  const arrayBuffer = await wb.xlsx.writeBuffer();
  return Buffer.from(arrayBuffer);
}

/**
 * Generate SASRA Loan Portfolio Aging & Risk Analysis Excel
 */
export async function generateLoanAgingPortfolioExcel(data: {
  categories: {
    classification: string;
    daysPastDue: string;
    numAccounts: number;
    outstandingBalance: number;
    requiredProvisionRate: number;
    provisionAmount: number;
  }[];
  totalPortfolio: number;
  totalProvisions: number;
  parRatio: number;
  dateStr?: string;
}): Promise<Buffer> {
  const wb = new ExcelJS.Workbook();
  wb.creator = 'Umoja SACCO Management System';
  const ws = wb.addWorksheet('Loan Portfolio Aging');
  ws.views = [{ showGridLines: true }];

  ws.addRow(['UMOJA DRIVERS CO-OPERATIVE SAVINGS & CREDIT SOCIETY LTD']);
  ws.getRow(1).font = { size: 14, bold: true, color: { argb: 'FF0B2419' } };
  ws.addRow(['SASRA LOAN PORTFOLIO AGING & RISK PROVISIONING SCHEDULE']);
  ws.getRow(2).font = { size: 12, bold: true, color: { argb: 'FF374151' } };
  ws.addRow([`As of: ${data.dateStr || new Date().toLocaleDateString('en-GB')} | Portfolio at Risk (PAR > 30): ${data.parRatio}%`]);
  ws.addRow([]);

  ws.addRow([
    'SASRA Classification',
    'Days Past Due',
    'No. of Loans',
    'Outstanding Balance (KES)',
    '% of Portfolio',
    'Provision Rate (%)',
    'Required Provision (KES)',
  ]);
  styleHeaderRow(ws.getRow(5));

  data.categories.forEach((cat) => {
    const pct = data.totalPortfolio > 0 ? (cat.outstandingBalance / data.totalPortfolio) * 100 : 0;
    ws.addRow([
      cat.classification,
      cat.daysPastDue,
      cat.numAccounts,
      cat.outstandingBalance,
      `${pct.toFixed(2)}%`,
      `${cat.requiredProvisionRate}%`,
      cat.provisionAmount,
    ]);
  });

  const summaryRow = ws.addRow([
    'TOTAL PORTFOLIO',
    '—',
    data.categories.reduce((acc, c) => acc + c.numAccounts, 0),
    data.totalPortfolio,
    '100.00%',
    '—',
    data.totalProvisions,
  ]);
  summaryRow.font = { bold: true };

  autoFitColumns(ws);

  const arrayBuffer = await wb.xlsx.writeBuffer();
  return Buffer.from(arrayBuffer);
}

/**
 * Generate Members Master Register Excel
 */
export async function generateMembersMasterRegisterExcel(members: any[]): Promise<Buffer> {
  const wb = new ExcelJS.Workbook();
  wb.creator = 'Umoja SACCO Management System';
  const ws = wb.addWorksheet('Members Master Register');
  ws.views = [{ state: 'frozen', ySplit: 5, showGridLines: true }];

  ws.addRow(['UMOJA DRIVERS CO-OPERATIVE SAVINGS & CREDIT SOCIETY LTD']);
  ws.getRow(1).font = { size: 14, bold: true, color: { argb: 'FF0B2419' } };
  ws.addRow(['OFFICIAL MEMBERSHIP REGISTER & EQUITY AUDIT SCHEDULE']);
  ws.getRow(2).font = { size: 12, bold: true, color: { argb: 'FF374151' } };
  ws.addRow([`Total Enrolled Members: ${members.length} | Export Date: ${new Date().toLocaleDateString('en-GB')}`]);
  ws.addRow([]);

  ws.addRow([
    'Member No.',
    'Full Name',
    'National ID',
    'Phone Number',
    'Email Address',
    'Join Date',
    'KYC Status',
    'Savings Balance (KES)',
    'Shares Capital (KES)',
    'Active Loans (KES)',
    'Status',
  ]);
  styleHeaderRow(ws.getRow(5));

  let rIdx = 6;
  members.forEach((m) => {
    ws.addRow([
      m.member_reg_no || `MEM-${m.member_id}`,
      m.full_name,
      m.national_id || '—',
      m.phone || '—',
      m.email || '—',
      m.join_date ? new Date(m.join_date).toLocaleDateString('en-GB') : '—',
      (m.kyc_status || 'not_submitted').toUpperCase(),
      Number(m.savings_balance || 0),
      Number(m.shares_balance || 0),
      Number(m.loans_balance || 0),
      (m.status || 'active').toUpperCase(),
    ]);
    rIdx++;
  });

  if (members.length > 0) {
    const sumRow = ws.addRow([
      'TOTALS',
      '',
      '',
      '',
      '',
      '',
      '',
      { formula: `SUM(H6:H${rIdx - 1})` },
      { formula: `SUM(I6:I${rIdx - 1})` },
      { formula: `SUM(J6:J${rIdx - 1})` },
      '',
    ]);
    sumRow.font = { bold: true };
  }

  autoFitColumns(ws);

  const arrayBuffer = await wb.xlsx.writeBuffer();
  return Buffer.from(arrayBuffer);
}

