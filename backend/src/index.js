import express from 'express';
import cors from 'cors';
import dotenv from 'dotenv';
import path from 'path';
import { fileURLToPath } from 'url';
import { optionalAuth } from './middleware/auth.js';
import { ensureUploadDir } from './utils/helpers.js';

import inventoryRoutes from './routes/inventory.js';
import customerRoutes from './routes/customers.js';
import eventRoutes from './routes/events.js';
import estimateRoutes from './routes/estimates.js';
import purchaseRoutes from './routes/purchases.js';
import saleRoutes from './routes/sales.js';
import partnerRoutes from './routes/partners.js';
import budgetRoutes from './routes/budgets.js';
import contractRoutes from './routes/contracts.js';
import reportRoutes from './routes/reports.js';
import settingsRoutes from './routes/settings.js';
import searchRoutes from './routes/search.js';
import attachmentRoutes from './routes/attachments.js';

dotenv.config();

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const app = express();
const PORT = process.env.PORT || 3001;

app.use(cors({ origin: process.env.FRONTEND_URL || 'http://localhost:5173' }));
app.use(express.json({ limit: '10mb' }));
app.use(optionalAuth);

const uploadDir = ensureUploadDir();
app.use('/uploads', express.static(uploadDir));

app.get('/api/health', (req, res) => {
  res.json({ status: 'ok', app: 'ThreadGlam Event Manager' });
});

app.use('/api/inventory', inventoryRoutes);
app.use('/api/customers', customerRoutes);
app.use('/api/events', eventRoutes);
app.use('/api/estimates', estimateRoutes);
app.use('/api/purchases', purchaseRoutes);
app.use('/api/sales', saleRoutes);
app.use('/api/partners', partnerRoutes);
app.use('/api/budgets', budgetRoutes);
app.use('/api/contracts', contractRoutes);
app.use('/api/reports', reportRoutes);
app.use('/api/settings', settingsRoutes);
app.use('/api/search', searchRoutes);
app.use('/api/attachments', attachmentRoutes);

app.use((err, req, res, next) => {
  console.error(err);
  res.status(500).json({ error: err.message || 'Internal server error' });
});

app.listen(PORT, () => {
  console.log(`ThreadGlam API running on http://localhost:${PORT}`);
});
