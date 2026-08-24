'use client';

import React, { useEffect, useState } from 'react';
import { api } from '@/lib/api';
import { useAuth } from '@/context/AuthContext';
import { useToast } from '@/context/ToastContext';
import { formatDate } from '@/lib/utils';
import { Bell, Check, CheckCheck } from 'lucide-react';

export default function MemberNotificationsPage() {
  const { refreshUser } = useAuth();
  const { toast } = useToast();
  const [notifications, setNotifications] = useState<any[]>([]);
  const [loading, setLoading] = useState(true);

  const fetchNotifs = async () => {
    try {
      const res = await api.get('/member/notifications');
      if (res.status === 'success') {
        setNotifications(res.data.notifications || []);
      }
    } catch (err) {
      console.error(err);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchNotifs();
  }, []);

  const markAllRead = async () => {
    try {
      await api.post('/member/mark_notif_read', {});
      toast.success('All notifications marked as read.');
      fetchNotifs();
      refreshUser();
    } catch (err: any) {
      toast.error(err.message || 'Failed to update notifications.');
    }
  };

  return (
    <div style={{ display: 'flex', flexDirection: 'column', gap: '28px', maxWidth: '800px' }}>
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', flexWrap: 'wrap', gap: '16px' }}>
        <div>
          <h1 style={{ fontSize: '1.75rem', fontWeight: 800, color: 'var(--text-main)' }}>Notifications</h1>
          <p style={{ color: 'var(--text-muted)', fontSize: '0.88rem' }}>Stay informed about loan approvals, payouts, and savings updates</p>
        </div>
        {notifications.some((n) => n.status === 'unread') && (
          <button onClick={markAllRead} className="btn btn-outline-forest">
            <CheckCheck size={16} /> Mark All as Read
          </button>
        )}
      </div>

      <div style={{ display: 'flex', flexDirection: 'column', gap: '12px' }}>
        {notifications.length ? (
          notifications.map((n, i) => (
            <div
              key={i}
              className="card"
              style={{
                padding: '18px 24px',
                borderLeft: n.status === 'unread' ? '4px solid var(--brand-lime)' : '1px solid var(--border-color)',
                backgroundColor: n.status === 'unread' ? 'var(--surface-2)' : 'var(--bg-surface)',
              }}
            >
              <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', marginBottom: '6px' }}>
                <h4 style={{ fontSize: '0.98rem', fontWeight: 700, color: 'var(--text-main)' }}>
                  {n.title || 'System Notification'}
                </h4>
                <span style={{ fontSize: '0.75rem', color: 'var(--text-muted)' }}>{formatDate(n.created_at)}</span>
              </div>
              <p style={{ color: 'var(--text-muted)', fontSize: '0.88rem', lineHeight: 1.5 }}>
                {n.message}
              </p>
            </div>
          ))
        ) : (
          <div className="card" style={{ textAlign: 'center', padding: '50px', color: 'var(--text-muted)' }}>
            <Bell size={40} style={{ margin: '0 auto 12px', opacity: 0.5 }} />
            <p>{loading ? 'Loading notifications...' : 'No notifications in your inbox.'}</p>
          </div>
        )}
      </div>
    </div>
  );
}
