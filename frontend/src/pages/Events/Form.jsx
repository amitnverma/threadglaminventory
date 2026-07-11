import { useEffect, useState } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import toast from 'react-hot-toast';
import api from '@/lib/api';
import PageHeader from '@/components/ui/PageHeader';
import Modal from '@/components/ui/Modal';
import { EVENT_STATUSES } from '@/lib/utils';

export default function EventForm() {
  const { id } = useParams();
  const navigate = useNavigate();
  const [customers, setCustomers] = useState([]);
  const [newCustomerOpen, setNewCustomerOpen] = useState(false);
  const [newCustomer, setNewCustomer] = useState({ name: '', email: '', phone: '' });
  const [form, setForm] = useState({
    customer_id: '', title: '', ceremony_type: '', event_date: '', end_date: '',
    venue: '', status: 'inquiry', internal_notes: '', archived: false,
  });
  const [saving, setSaving] = useState(false);

  useEffect(() => {
    api.get('/customers').then(({ data }) => setCustomers(data));
    if (id) api.get(`/events/${id}`).then(({ data }) => setForm(data));
  }, [id]);

  const handleSubmit = async (e) => {
    e.preventDefault();
    setSaving(true);
    try {
      if (id) {
        await api.put(`/events/${id}`, form);
        toast.success('Event updated');
        navigate(`/events/${id}`);
      } else {
        const { data } = await api.post('/events', form);
        toast.success('Event created');
        navigate(`/events/${data.id}`);
      }
    } catch {
      toast.error('Save failed');
    } finally {
      setSaving(false);
    }
  };

  const addCustomer = async () => {
    const { data } = await api.post('/customers', newCustomer);
    setCustomers([...customers, { id: data.id, ...newCustomer }]);
    setForm({ ...form, customer_id: data.id });
    setNewCustomerOpen(false);
    toast.success('Customer added');
  };

  const set = (field) => (e) => setForm({ ...form, [field]: e.target.type === 'checkbox' ? e.target.checked : e.target.value });

  return (
    <div>
      <PageHeader title={id ? 'Edit Event' : 'New Event'} breadcrumbs={[{ to: '/events', label: 'Events' }, { label: id ? 'Edit' : 'New' }]} />
      <form onSubmit={handleSubmit} className="card p-6 max-w-2xl">
        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div className="md:col-span-2">
            <label className="label">Customer *</label>
            <div className="flex gap-2">
              <select className="input flex-1" value={form.customer_id} onChange={set('customer_id')} required>
                <option value="">Select customer</option>
                {customers.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
              </select>
              <button type="button" className="btn-secondary whitespace-nowrap" onClick={() => setNewCustomerOpen(true)}>+ New</button>
            </div>
          </div>
          <div className="md:col-span-2">
            <label className="label">Event Title *</label>
            <input className="input" value={form.title} onChange={set('title')} required />
          </div>
          <div>
            <label className="label">Ceremony Type</label>
            <select className="input" value={form.ceremony_type} onChange={set('ceremony_type')}>
              <option value="">Select type</option>
              {['Wedding', 'Reception', 'Birthday', 'Corporate', 'Anniversary', 'Other'].map((t) => <option key={t} value={t}>{t}</option>)}
            </select>
          </div>
          <div>
            <label className="label">Status</label>
            <select className="input" value={form.status} onChange={set('status')}>
              {EVENT_STATUSES.map((s) => <option key={s.value} value={s.value}>{s.label}</option>)}
            </select>
          </div>
          <div>
            <label className="label">Event Date</label>
            <input type="date" className="input" value={form.event_date?.split('T')[0] || ''} onChange={set('event_date')} />
          </div>
          <div>
            <label className="label">End Date</label>
            <input type="date" className="input" value={form.end_date?.split('T')[0] || ''} onChange={set('end_date')} />
          </div>
          <div className="md:col-span-2">
            <label className="label">Venue</label>
            <input className="input" value={form.venue} onChange={set('venue')} />
          </div>
          <div className="md:col-span-2">
            <label className="label">Internal Notes</label>
            <textarea className="input" rows={3} value={form.internal_notes} onChange={set('internal_notes')} />
          </div>
          {id && (
            <div className="md:col-span-2">
              <label className="flex items-center gap-2">
                <input type="checkbox" checked={form.archived} onChange={set('archived')} />
                <span className="text-sm">Archive this event</span>
              </label>
            </div>
          )}
        </div>
        <div className="flex gap-3 mt-6">
          <button type="submit" className="btn-primary" disabled={saving}>{saving ? 'Saving...' : 'Save Event'}</button>
          <button type="button" className="btn-secondary" onClick={() => navigate(-1)}>Cancel</button>
        </div>
      </form>

      <Modal open={newCustomerOpen} onClose={() => setNewCustomerOpen(false)} title="Add Customer" size="sm">
        <div className="space-y-3">
          <div><label className="label">Name *</label><input className="input" value={newCustomer.name} onChange={(e) => setNewCustomer({ ...newCustomer, name: e.target.value })} /></div>
          <div><label className="label">Email</label><input className="input" value={newCustomer.email} onChange={(e) => setNewCustomer({ ...newCustomer, email: e.target.value })} /></div>
          <div><label className="label">Phone</label><input className="input" value={newCustomer.phone} onChange={(e) => setNewCustomer({ ...newCustomer, phone: e.target.value })} /></div>
          <button className="btn-primary w-full" onClick={addCustomer} disabled={!newCustomer.name}>Add Customer</button>
        </div>
      </Modal>
    </div>
  );
}
