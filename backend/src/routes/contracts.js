import { Router } from 'express';
import fs from 'fs';
import { query } from '../config/db.js';
import { getSettings } from '../utils/helpers.js';
import { getEstimateWithDetails } from '../services/estimateService.js';
import {
  buildContractData,
  replacePlaceholders,
  generateContractPdf,
  getPdfOutputPath,
} from '../services/contractPdfService.js';

const router = Router();

router.get('/', async (req, res) => {
  try {
    const contracts = await query(
      `SELECT c.*, cu.name as customer_name, e.title as event_title
       FROM contracts c
       JOIN customers cu ON cu.id = c.customer_id
       LEFT JOIN events e ON e.id = c.event_id
       ORDER BY c.updated_at DESC`
    );
    res.json(contracts);
  } catch (err) {
    res.status(500).json({ error: err.message });
  }
});

router.get('/templates', async (req, res) => {
  try {
    const templates = await query('SELECT * FROM contract_templates ORDER BY name');
    res.json(templates);
  } catch (err) {
    res.status(500).json({ error: err.message });
  }
});

router.post('/templates', async (req, res) => {
  try {
    const { name, content, is_default } = req.body;
    if (is_default) await query('UPDATE contract_templates SET is_default=0');
    const result = await query(
      'INSERT INTO contract_templates (name, content, is_default) VALUES (?,?,?)',
      [name, content, is_default ? 1 : 0]
    );
    res.status(201).json({ id: result.insertId });
  } catch (err) {
    res.status(500).json({ error: err.message });
  }
});

router.get('/:id', async (req, res) => {
  try {
    const [contract] = await query(
      `SELECT c.*, cu.name as customer_name, cu.email as customer_email, cu.phone as customer_phone,
        e.title as event_title, e.event_date, e.venue
       FROM contracts c
       JOIN customers cu ON cu.id = c.customer_id
       LEFT JOIN events e ON e.id = c.event_id
       WHERE c.id=?`,
      [req.params.id]
    );
    if (!contract) return res.status(404).json({ error: 'Contract not found' });
    res.json(contract);
  } catch (err) {
    res.status(500).json({ error: err.message });
  }
});

router.post('/', async (req, res) => {
  try {
    const d = req.body;
    const result = await query(
      'INSERT INTO contracts (customer_id, event_id, estimate_id, template_id, title, content, status) VALUES (?,?,?,?,?,?,?)',
      [
        d.customer_id, d.event_id || null, d.estimate_id || null, d.template_id || null,
        d.title, d.content, d.status || 'draft',
      ]
    );
    res.status(201).json({ id: result.insertId });
  } catch (err) {
    res.status(500).json({ error: err.message });
  }
});

router.post('/from-estimate/:estimateId', async (req, res) => {
  try {
    const estimate = await getEstimateWithDetails(req.params.estimateId);
    if (!estimate) return res.status(404).json({ error: 'Estimate not found' });

    const settings = await getSettings();
    let template;
    if (req.body.template_id) {
      [template] = await query('SELECT * FROM contract_templates WHERE id=?', [req.body.template_id]);
    } else {
      [template] = await query('SELECT * FROM contract_templates WHERE is_default=1 LIMIT 1');
    }

    const [customer] = await query('SELECT * FROM customers WHERE id=?', [estimate.customer_id]);
    const [event] = estimate.event_id
      ? await query('SELECT * FROM events WHERE id=?', [estimate.event_id])
      : [null];

    const placeholders = await buildContractData(null, customer, event, estimate, settings);
    const content = replacePlaceholders(template?.content || '<h1>Contract</h1>{{items_table}}', placeholders);

    const result = await query(
      'INSERT INTO contracts (customer_id, event_id, estimate_id, template_id, title, content, status) VALUES (?,?,?,?,?,?,?)',
      [
        estimate.customer_id, estimate.event_id, estimate.id, template?.id || null,
        req.body.title || `Contract - ${estimate.title}`, content, 'draft',
      ]
    );

    res.status(201).json({ id: result.insertId, content });
  } catch (err) {
    res.status(500).json({ error: err.message });
  }
});

router.put('/:id', async (req, res) => {
  try {
    const { title, content, status } = req.body;
    const updates = [];
    const params = [];
    if (title !== undefined) { updates.push('title=?'); params.push(title); }
    if (content !== undefined) { updates.push('content=?'); params.push(content); }
    if (status !== undefined) {
      updates.push('status=?');
      params.push(status);
      if (status === 'signed') updates.push('signed_at=NOW()');
    }
    updates.push('updated_at=NOW()');
    params.push(req.params.id);

    await query(`UPDATE contracts SET ${updates.join(', ')} WHERE id=?`, params);
    res.json({ success: true });
  } catch (err) {
    res.status(500).json({ error: err.message });
  }
});

router.get('/:id/preview', async (req, res) => {
  try {
    const [contract] = await query('SELECT * FROM contracts WHERE id=?', [req.params.id]);
    if (!contract) return res.status(404).json({ error: 'Contract not found' });

    const settings = await getSettings();
    const [customer] = await query('SELECT * FROM customers WHERE id=?', [contract.customer_id]);
    const [event] = contract.event_id ? await query('SELECT * FROM events WHERE id=?', [contract.event_id]) : [null];
    let estimate = null;
    if (contract.estimate_id) estimate = await getEstimateWithDetails(contract.estimate_id);

    const placeholders = await buildContractData(contract, customer, event, estimate, settings);
    const rendered = replacePlaceholders(contract.content, placeholders);
    res.json({ rendered, placeholders });
  } catch (err) {
    res.status(500).json({ error: err.message });
  }
});

router.get('/:id/pdf', async (req, res) => {
  try {
    const [contract] = await query('SELECT * FROM contracts WHERE id=?', [req.params.id]);
    if (!contract) return res.status(404).json({ error: 'Contract not found' });

    const settings = await getSettings();
    const [customer] = await query('SELECT * FROM customers WHERE id=?', [contract.customer_id]);
    const [event] = contract.event_id ? await query('SELECT * FROM events WHERE id=?', [contract.event_id]) : [null];
    let estimate = null;
    if (contract.estimate_id) estimate = await getEstimateWithDetails(contract.estimate_id);

    const placeholders = await buildContractData(contract, customer, event, estimate, settings);
    const outputPath = getPdfOutputPath(contract.id);
    await generateContractPdf(contract, placeholders, outputPath);

    res.download(outputPath, `contract-${contract.id}.pdf`, () => {
      fs.unlink(outputPath, () => {});
    });
  } catch (err) {
    res.status(500).json({ error: err.message });
  }
});

router.delete('/:id', async (req, res) => {
  try {
    await query('DELETE FROM contracts WHERE id=?', [req.params.id]);
    res.json({ success: true });
  } catch (err) {
    res.status(500).json({ error: err.message });
  }
});

export default router;
