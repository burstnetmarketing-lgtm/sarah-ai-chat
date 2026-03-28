import { GROUPS } from '../components/api-guide/apiGuideData.js';
import { GroupPanel } from '../components/api-guide/ApiGuideComponents.jsx';

const BASE = '/wp-json/sarah-ai-server/v1';

export default function ApiGuide({ filter = 'all' }) {
  const visibleGroups = filter === 'public' ? GROUPS.filter(g => g.badge === 'bg-success')
                      : filter === 'admin'  ? GROUPS.filter(g => g.badge === 'bg-primary')
                      : filter === 'client' ? GROUPS.filter(g => g.badge === 'bg-info')
                      : GROUPS;

  const visibleCount = visibleGroups.reduce((n, g) => n + g.endpoints.length, 0);

  const filterLabel = filter === 'public' ? 'Public endpoints only (no WP login — auth via account_key + site_key).'
                    : filter === 'admin'  ? 'Admin endpoints — require WordPress admin login (manage_options).'
                    : filter === 'client' ? 'Client endpoints — auth via account_key + site_key + X-Sarah-Platform-Key header.'
                    : 'All REST endpoints exposed by sarah-ai-server.';

  return (
    <>
      <div className="mb-3">
        <p className="text-muted small mb-0">
          {filterLabel} Base URL: <code className="small">{'{site_url}'}{BASE}</code> &nbsp;·&nbsp; {visibleCount} endpoints
        </p>
      </div>

      <div className="card border-0 shadow-sm">
        <div className="card-body">
          {visibleGroups.map(g => <GroupPanel key={g.id} group={g} />)}
        </div>
      </div>
    </>
  );
}
