import { useEffect, useState } from 'react';
import { useParams } from 'react-router-dom';
import { useEditor, EditorContent } from '@tiptap/react';
import StarterKit from '@tiptap/starter-kit';
import Placeholder from '@tiptap/extension-placeholder';
import { Save, Download, Eye } from 'lucide-react';
import toast from 'react-hot-toast';
import api from '@/lib/api';
import PageHeader, { StatusBadge } from '@/components/ui/PageHeader';
import { CONTRACT_STATUSES } from '@/lib/utils';

export default function ContractEditor() {
  const { id } = useParams();
  const [contract, setContract] = useState(null);
  const [preview, setPreview] = useState('');
  const [showPreview, setShowPreview] = useState(false);
  const [saving, setSaving] = useState(false);

  const editor = useEditor({
    extensions: [StarterKit, Placeholder.configure({ placeholder: 'Edit contract content...' })],
    content: '',
    onUpdate: ({ editor }) => setContract((prev) => prev ? { ...prev, content: editor.getHTML() } : prev),
  });

  useEffect(() => {
    api.get(`/contracts/${id}`).then(({ data }) => {
      setContract(data);
      editor?.commands.setContent(data.content || '');
    });
  }, [id, editor]);

  const handleSave = async () => {
    setSaving(true);
    try {
      await api.put(`/contracts/${id}`, { title: contract.title, content: contract.content, status: contract.status });
      toast.success('Contract saved');
    } catch {
      toast.error('Save failed');
    } finally {
      setSaving(false);
    }
  };

  const handlePreview = async () => {
    const { data } = await api.get(`/contracts/${id}/preview`);
    setPreview(data.rendered);
    setShowPreview(true);
  };

  const handleDownload = () => {
    window.open(`/api/contracts/${id}/pdf`, '_blank');
  };

  if (!contract) return <div className="animate-pulse h-64 bg-gray-200 rounded-xl" />;

  return (
    <div>
      <PageHeader
        title={contract.title}
        breadcrumbs={[{ to: '/contracts', label: 'Contracts' }, { label: contract.title }]}
        action={
          <div className="flex gap-2 items-center">
            <StatusBadge status={contract.status} list={CONTRACT_STATUSES} />
            <button className="btn-secondary" onClick={handlePreview}><Eye className="w-4 h-4" /> Preview</button>
            <button className="btn-secondary" onClick={handleDownload}><Download className="w-4 h-4" /> PDF</button>
            <button className="btn-primary" onClick={handleSave} disabled={saving}><Save className="w-4 h-4" /> {saving ? 'Saving...' : 'Save'}</button>
          </div>
        }
      />

      <div className="grid grid-cols-1 lg:grid-cols-4 gap-4">
        <div className="lg:col-span-3 card overflow-hidden">
          <div className="border-b border-gray-100 p-2 flex gap-1 flex-wrap">
            {['bold', 'italic', 'strike'].map((cmd) => (
              <button key={cmd} className="btn-ghost text-xs px-2 py-1 capitalize" onClick={() => editor?.chain().focus()[`toggle${cmd.charAt(0).toUpperCase() + cmd.slice(1)}`]().run()}>{cmd}</button>
            ))}
            <button className="btn-ghost text-xs px-2 py-1" onClick={() => editor?.chain().focus().toggleHeading({ level: 2 }).run()}>H2</button>
            <button className="btn-ghost text-xs px-2 py-1" onClick={() => editor?.chain().focus().toggleBulletList().run()}>List</button>
          </div>
          <EditorContent editor={editor} className="min-h-[500px]" />
        </div>

        <div className="space-y-4">
          <div className="card p-4">
            <label className="label">Status</label>
            <select className="input" value={contract.status} onChange={(e) => setContract({ ...contract, status: e.target.value })}>
              {CONTRACT_STATUSES.map((s) => <option key={s.value} value={s.value}>{s.label}</option>)}
            </select>
          </div>
          <div className="card p-4">
            <h3 className="font-semibold text-sm mb-2">Placeholders</h3>
            <div className="space-y-1 text-xs text-gray-500">
              {['{{customer_name}}', '{{event_date}}', '{{event_venue}}', '{{items_table}}', '{{total}}', '{{company_name}}'].map((p) => (
                <button key={p} className="block w-full text-left hover:text-brand-600 font-mono" onClick={() => editor?.chain().focus().insertContent(p).run()}>{p}</button>
              ))}
            </div>
          </div>
          <div className="card p-4 text-sm space-y-2">
            <div><span className="text-gray-500">Customer:</span> {contract.customer_name}</div>
            <div><span className="text-gray-500">Event:</span> {contract.event_title || '—'}</div>
          </div>
        </div>
      </div>

      {showPreview && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
          <div className="absolute inset-0 bg-black/50" onClick={() => setShowPreview(false)} />
          <div className="relative bg-white rounded-xl shadow-xl w-full max-w-3xl max-h-[90vh] overflow-y-auto p-6">
            <h2 className="text-lg font-semibold mb-4">Contract Preview</h2>
            <div className="prose max-w-none" dangerouslySetInnerHTML={{ __html: preview }} />
            <button className="btn-primary mt-4" onClick={() => setShowPreview(false)}>Close</button>
          </div>
        </div>
      )}
    </div>
  );
}
