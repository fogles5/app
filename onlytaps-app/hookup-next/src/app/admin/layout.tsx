import { requireAdmin } from '@/lib/admin';

export const metadata = { title: 'Admin · Turnpage', robots: { index: false, follow: false } };

export default async function AdminLayout({ children }: { children: React.ReactNode }) {
  await requireAdmin();
  return (
    <div className="page">
      <nav className="admin-nav">
        <a href="/admin/dashboard.php">Dashboard</a>
        <a href="/admin/users.php">Users</a>
        <a href="/admin/listings.php">Listings</a>
        <a href="/admin/reports.php">Reports</a>
        <a href="/admin/categories.php">Categories</a>
        <span className="muted">More:</span>
        <a href="/admin/settings.php">Settings</a>
        <a href="/admin/announcements.php">Announcements</a>
        <a href="/admin/advertising.php">Advertising</a>
        <a href="/admin/security-dashboard.php">Security</a>
      </nav>
      {children}
    </div>
  );
}
