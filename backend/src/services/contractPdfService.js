import PDFDocument from 'pdfkit';
import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';
import { formatCurrency, getSettings } from '../utils/helpers.js';

const __dirname = path.dirname(fileURLToPath(import.meta.url));

export function replacePlaceholders(content, data) {
  let result = content;
  for (const [key, value] of Object.entries(data)) {
    result = result.replace(new RegExp(`\\{\\{${key}\\}\\}`, 'g'), value ?? '');
  }
  return result;
}

export function buildItemsTableHtml(lineItems, currency) {
  if (!lineItems?.length) return '<p>No items listed.</p>';

  const rows = lineItems
    .filter((i) => i.line_type !== 'discount')
    .map(
      (item) =>
        `<tr>
          <td style="padding:8px;border:1px solid #ddd;">${item.label}</td>
          <td style="padding:8px;border:1px solid #ddd;text-align:center;">${item.quantity}</td>
          <td style="padding:8px;border:1px solid #ddd;text-align:right;">${formatCurrency(item.unit_price, currency)}</td>
          <td style="padding:8px;border:1px solid #ddd;text-align:right;">${formatCurrency(item.quantity * item.unit_price, currency)}</td>
        </tr>`
    )
    .join('');

  return `<table style="width:100%;border-collapse:collapse;margin:16px 0;">
    <thead><tr style="background:#f3f4f6;">
      <th style="padding:8px;border:1px solid #ddd;text-align:left;">Item</th>
      <th style="padding:8px;border:1px solid #ddd;">Qty</th>
      <th style="padding:8px;border:1px solid #ddd;text-align:right;">Rate</th>
      <th style="padding:8px;border:1px solid #ddd;text-align:right;">Amount</th>
    </tr></thead><tbody>${rows}</tbody></table>`;
}

export async function buildContractData(contract, customer, event, estimate, settings) {
  const currency = settings.currency || 'INR';
  const lineItems = estimate?.line_items || [];

  return {
    company_name: settings.company_name,
    company_address: settings.company_address,
    company_phone: settings.company_phone,
    company_email: settings.company_email,
    customer_name: customer?.name || '',
    customer_email: customer?.email || '',
    customer_phone: customer?.phone || '',
    event_title: event?.title || estimate?.title || '',
    event_date: event?.event_date ? new Date(event.event_date).toLocaleDateString() : '',
    event_venue: event?.venue || '',
    subtotal: formatCurrency(estimate?.subtotal, currency),
    tax_percent: estimate?.tax_percent || settings.default_tax_percent,
    tax_amount: formatCurrency(estimate?.tax_amount, currency),
    discount_amount: formatCurrency(estimate?.discount_amount, currency),
    total: formatCurrency(estimate?.total, currency),
    contract_footer: settings.contract_footer || '',
    items_table: buildItemsTableHtml(lineItems, currency),
  };
}

export async function generateContractPdf(contract, placeholders, outputPath) {
  return new Promise((resolve, reject) => {
    const doc = new PDFDocument({ margin: 50, size: 'A4' });
    const stream = fs.createWriteStream(outputPath);
    doc.pipe(stream);

    const content = replacePlaceholders(contract.content, placeholders);
    const plainText = content
      .replace(/<br\s*\/?>/gi, '\n')
      .replace(/<\/p>/gi, '\n\n')
      .replace(/<\/h[1-6]>/gi, '\n\n')
      .replace(/<[^>]+>/g, '')
      .replace(/&nbsp;/g, ' ')
      .replace(/&amp;/g, '&')
      .trim();

    doc.fontSize(10).font('Helvetica');
    const lines = plainText.split('\n');
    for (const line of lines) {
      if (line.trim()) {
        if (line.length < 60 && line === line.toUpperCase()) {
          doc.fontSize(14).font('Helvetica-Bold').text(line, { continued: false });
          doc.fontSize(10).font('Helvetica');
        } else {
          doc.text(line, { align: 'left' });
        }
      } else {
        doc.moveDown(0.5);
      }
    }

    doc.end();
    stream.on('finish', () => resolve(outputPath));
    stream.on('error', reject);
  });
}

export function getPdfOutputPath(contractId) {
  const dir = path.resolve(__dirname, '../../uploads/contracts');
  if (!fs.existsSync(dir)) fs.mkdirSync(dir, { recursive: true });
  return path.join(dir, `contract-${contractId}-${Date.now()}.pdf`);
}
