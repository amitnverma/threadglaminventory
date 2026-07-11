import { Router } from 'express';
import { query } from '../config/db.js';
import { getSettings } from '../utils/helpers.js';

const router = Router();

router.get('/', async (req, res) => {
  try {
    const settings = await getSettings();
    res.json(settings);
  } catch (err) {
    res.status(500).json({ error: err.message });
  }
});

router.put('/', async (req, res) => {
  try {
    const d = req.body;
    await query(
      `UPDATE settings SET company_name=?, company_address=?, company_phone=?, company_email=?,
       logo_path=?, default_tax_percent=?, currency=?, contract_footer=?, pdf_header=?, updated_at=NOW()
       WHERE id=1`,
      [
        d.company_name, d.company_address || null, d.company_phone || null,
        d.company_email || null, d.logo_path || null, d.default_tax_percent || 0,
        d.currency || 'INR', d.contract_footer || null, d.pdf_header || null,
      ]
    );
    const settings = await getSettings();
    res.json(settings);
  } catch (err) {
    res.status(500).json({ error: err.message });
  }
});

export default router;
