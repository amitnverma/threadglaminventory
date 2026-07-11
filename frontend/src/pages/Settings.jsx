import { useEffect, useState } from 'react';
import { Save } from 'lucide-react';
import toast from 'react-hot-toast';
import api from '@/lib/api';
import PageHeader from '@/components/ui/PageHeader';

export default function SettingsPage() {
  const [form, setForm] = useState({
    company_name: '', company_address: '', company_phone: '', company_email: '',
    default_tax_percent: 18, currency: 'INR', contract_footer: '', pdf_header: '',
  });
  const [saving, setSaving] = useState(false);
  const [adminPassword, setAdminPassword] = useState(localStorage.getItem('admin_password') || '');

  useEffect(() => {
    api.get('/settings').then(({ data }) => setForm(data));
  }, []);

  const handleSave = async () => {
    setSaving(true);
    try {
      await api.put('/settings', form);
      if (adminPassword) localStorage.setItem('admin_password', adminPassword);
      else localStorage.removeItem('admin_password');
      toast.success('Settings saved');
    } catch {
      toast.error('Save failed');
    } finally {
      setSaving(false);
    }
  };

  const set = (field) => (e) => setForm({ ...form, [field]: e.target.value });

  return (
    <div>
      <PageHeader title="Settings" subtitle="Company profile and app configuration" action={<button className="btn-primary" onClick={handleSave} disabled={saving}><Save className="w-4 h-4" /> {saving ? 'Saving...' : 'Save'}</button>} />

      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div className="card p-6 space-y-4">
          <h3 className="font-semibold">Company Profile</h3>
          <div><label className="label">Company Name</label><input className="input" value={form.company_name} onChange={set('company_name')} /></div>
          <div><label className="label">Address</label><textarea className="input" rows={2} value={form.company_address || ''} onChange={set('company_address')} /></div>
          <div className="grid grid-cols-2 gap-3">
            <div><label className="label">Phone</label><input className="input" value={form.company_phone || ''} onChange={set('company_phone')} /></div>
            <div><label className="label">Email</label><input className="input" value={form.company_email || ''} onChange={set('company_email')} /></div>
          </div>
        </div>

        <div className="card p-6 space-y-4">
          <h3 className="font-semibold">Financial Defaults</h3>
          <div className="grid grid-cols-2 gap-3">
            <div><label className="label">Default Tax %</label><input type="number" className="input" value={form.default_tax_percent} onChange={set('default_tax_percent')} /></div>
            <div><label className="label">Currency</label>
              <select className="input" value={form.currency} onChange={set('currency')}>
                <option value="INR">INR (₹)</option>
                <option value="USD">USD ($)</option>
              </select>
            </div>
          </div>
          <div><label className="label">Contract Footer Text</label><textarea className="input" rows={3} value={form.contract_footer || ''} onChange={set('contract_footer')} /></div>
          <div><label className="label">PDF Header Text</label><input className="input" value={form.pdf_header || ''} onChange={set('pdf_header')} /></div>
        </div>

        <div className="card p-6 space-y-4">
          <h3 className="font-semibold">Security (Optional)</h3>
          <p className="text-sm text-gray-500">Set an admin password to protect API access. Leave empty to disable.</p>
          <div><label className="label">Admin Password</label><input type="password" className="input" value={adminPassword} onChange={(e) => setAdminPassword(e.target.value)} placeholder="Leave empty to disable" /></div>
        </div>
      </div>
    </div>
  );
}
