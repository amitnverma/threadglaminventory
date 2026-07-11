import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { Plus, FileText } from 'lucide-react';
import api from '@/lib/api';
import PageHeader, { EmptyState, StatusBadge } from '@/components/ui/PageHeader';
import { ESTIMATE_STATUSES, formatCurrency } from '@/lib/utils';

export default function EstimatesIndex() {
  const [estimates, setEstimates] = useState([]);
  const [templates, setTemplates] = useState([]);
  const [showTemplates, setShowTemplates] = useState(false);

  useEffect(() => {
    api.get('/estimates').then(({ data }) => setEstimates(data));
    api.get('/estimates', { params: { templates: true } }).then(({ data }) => setTemplates(data));
  }, []);

  const list = showTemplates ? templates : estimates;

  return (
    <div>
      <PageHeader
        title="Estimates"
        subtitle="Create and manage event cost estimates"
        action={<Link to="/estimates/new" className="btn-primary"><Plus className="w-4 h-4" /> New Estimate</Link>}
      />

      <div className="flex gap-2 mb-4">
        <button className={`btn-secondary ${!showTemplates ? 'bg-brand-50 text-brand-700' : ''}`} onClick={() => setShowTemplates(false)}>All Estimates</button>
        <button className={`btn-secondary ${showTemplates ? 'bg-brand-50 text-brand-700' : ''}`} onClick={() => setShowTemplates(true)}><FileText className="w-4 h-4" /> Templates</button>
      </div>

      {list.length === 0 ? (
        <EmptyState
          title={showTemplates ? 'No templates' : 'No estimates yet'}
          description="Build your first estimate with drag-and-drop inventory items."
          action={<Link to="/estimates/new" className="btn-primary">Create Estimate</Link>}
        />
      ) : (
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
          {list.map((est) => (
            <Link key={est.id} to={`/estimates/${est.id}`} className="card p-5 hover:shadow-md transition-shadow">
              <div className="flex justify-between items-start mb-3">
                <h3 className="font-semibold">{est.title}</h3>
                {!showTemplates && <StatusBadge status={est.status} list={ESTIMATE_STATUSES} />}
                {showTemplates && <span className="badge bg-purple-100 text-purple-800">Template</span>}
              </div>
              <p className="text-sm text-gray-500 mb-3">{est.customer_name || 'No customer'}</p>
              <p className="text-lg font-bold text-brand-600">{formatCurrency(est.total)}</p>
            </Link>
          ))}
        </div>
      )}
    </div>
  );
}
