USE threadglam;

INSERT INTO settings (id, company_name, company_address, company_phone, company_email, default_tax_percent, currency, contract_footer, pdf_header, ceremony_types)
VALUES (1, 'ThreadGlam Events', '123 Main Street, Suite 200, New York, NY 10001', '(555) 123-4567', 'hello@threadglam.com', 8.875, 'USD',
'• Payment: 50% deposit upon signing, 50% balance due on or before the event date.
• Delivery: All items delivered and set up 2 hours before event start unless otherwise agreed.
• Damage/Loss: Client is responsible for damage or loss of rented items during the event period.
• Force Majeure: Neither party liable for delays due to weather, government restrictions, or acts of God.
• Venue Access: Client must ensure venue access, power supply, and necessary permits.
• Changes: Any changes to scope must be agreed in writing and may affect pricing.
• Governing Law: This agreement is governed by the laws of the State of New York, United States.',
'Professional Event Management & Decor Services',
'Wedding\nReception\nBirthday\nCorporate\nAnniversary\nEngagement\nBaby Shower\nGraduation\nOther')
ON DUPLICATE KEY UPDATE company_name = VALUES(company_name);

INSERT INTO inventory_categories (name, description) VALUES
('Decor', 'Stage and venue decoration items'),
('Furniture', 'Tables, chairs, and seating'),
('Lighting', 'LED lights, spotlights, and effects'),
('Linens', 'Tablecloths, drapes, and fabric'),
('Audio/Visual', 'Sound systems and displays');

INSERT INTO inventory_items (category_id, name, sku, description, quantity_on_hand, unit_cost, rental_price, sale_price, condition_status, reorder_level) VALUES
(1, 'Floral Stage Backdrop', 'DEC-001', 'Premium floral backdrop panel 8x6 ft', 5, 180.00, 95.00, 300.00, 'excellent', 1),
(2, 'Chiavari Gold Chair', 'FUR-001', 'Elegant gold chiavari chair', 200, 45.00, 6.00, 80.00, 'good', 40),
(3, 'LED Par Can Light', 'LGT-001', 'RGB LED par can with DMX', 40, 65.00, 10.00, 120.00, 'good', 8),
(4, 'Ivory Satin Tablecloth', 'LIN-001', '120 inch round ivory satin tablecloth', 80, 15.00, 3.00, 25.00, 'good', 16),
(5, 'Wireless Microphone Set', 'AV-001', 'Dual wireless mic with receiver', 10, 220.00, 45.00, 320.00, 'excellent', 2);

INSERT INTO customers (name, email, phone, address, notes) VALUES
('Sarah & Michael Johnson', 'sarah.johnson@email.com', '(555) 987-6543', '456 Oak Avenue, Brooklyn, NY 11201', 'Wedding client - referred by venue');

INSERT INTO events (customer_id, title, ceremony_type, event_date, venue, status, internal_notes) VALUES
(1, 'Johnson Wedding Reception', 'Wedding', '2026-08-15', 'Grand Ballroom, The Plaza Hotel', 'estimated', 'Prefer gold and ivory theme. 300 guests expected.');

INSERT INTO partners (name, phone, email, default_split_percent) VALUES
('Elite Decor Co.', '(555) 234-5678', 'contact@elitedecor.com', 30),
('SoundWave Audio', '(555) 345-6789', 'info@soundwaveaudio.com', 25);

INSERT INTO contract_templates (name, content, is_default) VALUES
('Comprehensive Event Agreement', '<h1 style="text-align:center;color:#5b21b6;">EVENT SERVICE AGREEMENT</h1>
<p style="text-align:center;">Agreement No: {{contract_number}} | Date: {{contract_date}}</p>
<h2>1. Parties</h2>
<p><strong>Service Provider:</strong> {{company_name}}<br>Address: {{company_address}}<br>Phone: {{company_phone}} | Email: {{company_email}}</p>
<p><strong>Client:</strong> {{customer_name}}<br>Phone: {{customer_phone}} | Email: {{customer_email}}<br>Address: {{customer_address}}</p>
<h2>2. Event Details</h2>
<p><strong>Event:</strong> {{event_title}}<br><strong>Date:</strong> {{event_date}}<br><strong>Venue:</strong> {{event_venue}}<br><strong>Type:</strong> {{event_type}}</p>
<h2>3. Services & Items</h2>
{{items_table}}
<h2>4. Payment Summary</h2>
<p>Subtotal: {{subtotal}} | Tax ({{tax_percent}}%): {{tax_amount}} | Discount: {{discount_amount}}<br><strong>Total: {{total}}</strong></p>
<h2>5. Terms & Conditions</h2>
<p>{{contract_footer}}</p>
<h2>6. Signatures</h2>
<p>Client: _________________________ Date: _____________</p>
<p>Company: _________________________ Date: _____________</p>', 1);
