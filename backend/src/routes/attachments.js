import { Router } from 'express';
import path from 'path';
import fs from 'fs';
import sharp from 'sharp';
import { query } from '../config/db.js';
import { upload } from '../middleware/upload.js';
import { ensureUploadDir } from '../utils/helpers.js';

const router = Router();

router.get('/:type/:id', async (req, res) => {
  try {
    const images = await query(
      'SELECT * FROM attachments WHERE attachable_type=? AND attachable_id=? ORDER BY sort_order',
      [req.params.type, req.params.id]
    );
    res.json(images);
  } catch (err) {
    res.status(500).json({ error: err.message });
  }
});

router.post('/:type/:id', upload.single('file'), async (req, res) => {
  try {
    if (!req.file) return res.status(400).json({ error: 'No file uploaded' });

    const type = req.params.type;
    const id = req.params.id;
    const uploadDir = ensureUploadDir();
    const subDir = path.join(uploadDir, type);
    if (!fs.existsSync(subDir)) fs.mkdirSync(subDir, { recursive: true });

    const relativePath = path.join(type, req.file.filename);
    let thumbnailPath = null;

    if (req.file.mimetype.startsWith('image/')) {
      const thumbName = `thumb-${req.file.filename}`;
      const thumbFull = path.join(subDir, thumbName);
      await sharp(req.file.path).resize(300, 300, { fit: 'inside' }).jpeg({ quality: 80 }).toFile(thumbFull);
      thumbnailPath = path.join(type, thumbName);
    }

    const [countRow] = await query(
      'SELECT COALESCE(MAX(sort_order), -1) as max_order FROM attachments WHERE attachable_type=? AND attachable_id=?',
      [type, id]
    );

    const result = await query(
      'INSERT INTO attachments (attachable_type, attachable_id, file_path, thumbnail_path, caption, sort_order) VALUES (?,?,?,?,?,?)',
      [type, id, relativePath, thumbnailPath, req.body.caption || null, countRow.max_order + 1]
    );

    res.status(201).json({
      id: result.insertId,
      file_path: relativePath,
      thumbnail_path: thumbnailPath,
      url: `/uploads/${relativePath}`,
      thumbnail_url: thumbnailPath ? `/uploads/${thumbnailPath}` : null,
    });
  } catch (err) {
    res.status(500).json({ error: err.message });
  }
});

router.delete('/:attachmentId', async (req, res) => {
  try {
    const [attachment] = await query('SELECT * FROM attachments WHERE id=?', [req.params.attachmentId]);
    if (!attachment) return res.status(404).json({ error: 'Attachment not found' });

    const uploadDir = ensureUploadDir();
    const filePath = path.join(uploadDir, attachment.file_path);
    if (fs.existsSync(filePath)) fs.unlinkSync(filePath);
    if (attachment.thumbnail_path) {
      const thumbPath = path.join(uploadDir, attachment.thumbnail_path);
      if (fs.existsSync(thumbPath)) fs.unlinkSync(thumbPath);
    }

    await query('DELETE FROM attachments WHERE id=?', [req.params.attachmentId]);
    res.json({ success: true });
  } catch (err) {
    res.status(500).json({ error: err.message });
  }
});

export default router;
