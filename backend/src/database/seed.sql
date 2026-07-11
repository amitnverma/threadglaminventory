USE threadglam;

INSERT INTO settings (id, company_name, company_address, company_phone, company_email, default_tax_percent, currency, contract_footer, pdf_header)
VALUES (1, 'ThreadGlam Events', '123 Event Street, Mumbai', '+91 98765 43210', 'hello@threadglam.com', 18, 'INR',
'Payment terms: 50% advance, 50% on event day. Cancellation within 7 days incurs 25% fee.',
'Professional Event Management Services')
ON DUPLICATE KEY UPDATE company_name = VALUES(company_name);

INSERT INTO inventory_categories (name, description) VALUES
('Decor', 'Stage and venue decoration items'),
('Furniture', 'Tables, chairs, and seating'),
('Lighting', 'LED lights, spotlights, and effects'),
('Linens', 'Tablecloths, drapes, and fabric'),
('Audio/Visual', 'Sound systems and displays');

INSERT INTO inventory_items (category_id, name, sku, description, quantity_on_hand, unit_cost, rental_price, sale_price, condition_status, reorder_level) VALUES
(1, 'Floral Stage Backdrop', 'DEC-001', 'Premium floral backdrop panel 8x6 ft', 5, 15000, 8000, 25000, 'excellent', 2),
(2, 'Chiavari Gold Chair', 'FUR-001', 'Elegant gold chiavari chair', 200, 2500, 350, 4500, 'good', 50),
(3, 'LED Par Can Light', 'LGT-001', 'RGB LED par can with DMX', 40, 3500, 500, 6000, 'good', 10),
(4, 'Ivory Satin Tablecloth', 'LIN-001', '120 inch round ivory satin tablecloth', 80, 800, 150, 1200, 'good', 20),
(5, 'Wireless Microphone Set', 'AV-001', 'Dual wireless mic with receiver', 10, 12000, 2500, 18000, 'excellent', 3);

INSERT INTO customers (name, email, phone, address, notes) VALUES
('Priya & Rahul Sharma', 'priya.sharma@email.com', '+91 99887 76655', 'Andheri West, Mumbai', 'Wedding client - referred by venue');

INSERT INTO events (customer_id, title, ceremony_type, event_date, venue, status, internal_notes) VALUES
(1, 'Sharma Wedding Reception', 'Wedding', '2026-08-15', 'Grand Ballroom, Taj Lands End', 'estimated', 'Prefer gold and ivory theme. 300 guests expected.');

INSERT INTO partners (name, phone, email, default_split_percent) VALUES
('Rajesh Decor Services', '+91 98123 45678', 'rajesh@decor.com', 30),
('SoundWave Audio', '+91 97654 32109', 'soundwave@audio.com', 25);

INSERT INTO contract_templates (name, content, is_default) VALUES
('Standard Event Contract', '<h1>Event Service Agreement</h1>
<p>This agreement is entered into between <strong>{{company_name}}</strong> and <strong>{{customer_name}}</strong> for the event titled <strong>{{event_title}}</strong> scheduled on <strong>{{event_date}}</strong> at <strong>{{event_venue}}</strong>.</p>
<h2>Services & Items</h2>
{{items_table}}
<h2>Payment Summary</h2>
<p>Subtotal: {{subtotal}}</p>
<p>Tax ({{tax_percent}}%): {{tax_amount}}</p>
<p>Discount: {{discount_amount}}</p>
<p><strong>Total: {{total}}</strong></p>
<h2>Terms & Conditions</h2>
<p>{{contract_footer}}</p>
<h2>Signatures</h2>
<p>Client Signature: _________________________ Date: _____________</p>
<p>Company Representative: _________________________ Date: _____________</p>', 1);
