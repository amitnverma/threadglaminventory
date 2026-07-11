import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { Plus } from 'lucide-react';
import api from '@/lib/api';
import PageHeader, { EmptyState, StatusBadge } from '@/components/ui/PageHeader';
import { CONTRACT_STATUSES, formatCurrency } from '@/lib/utils';

export default function ContractsIndex() {
  const [contracts, setContracts] = useState([]);

  useEffect(() => {
    api.get('/contracts').then(({ data }) => setContracts(data));
  }, []);

  return (
    <div>
      <PageHeader title="Contracts" subtitle="Create and manage client contracts" />
      {contracts.length === 0 ? (
        <EmptyState title="No contracts yet" description="Create a contract from an approved estimate." action={<Link to="/estimates" className="btn-primary">Go to Estimates</Link>} />
      ) : (
        <div className="space-y-3">
          {contracts.map((c) => (
            <Link key={c.id} to={`/contracts/${c.id}`} className="card p-4 flex justify-between items-center hover:shadow-md transition-shadow">
              <div>
                <h3 className="font-semibold">{c.title}</h3>
                <p className="text-sm text-gray-500">{c.customer_name} · {c.event_title || 'No event'}</p>
              </div>
              <StatusBadge status={c.status} list={CONTRACT_STATUSES} />
            </Link>
          ))}
        </div>
      )}
    </div>
  );
}
