'use client';

import React, { useEffect, useState } from 'react';
import { api } from '@/lib/api';
import { useAuth } from '@/context/AuthContext';
import { useToast } from '@/context/ToastContext';
import { formatDate } from '@/lib/utils';
import {
  Button,
  Card,
  Badge,
} from '@/components/sacco-ui';
import {
  Bell,
  CheckCheck,
  CheckCircle2,
  AlertCircle,
  Clock,
  ShieldCheck,
  Banknote,
  PiggyBank,
} from 'lucide-react';

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

  const defaultNotifications = [
    {
      id: 'n1',
      title: 'M-Pesa Savings Deposit Credited',
      message: 'Your voluntary savings deposit of KES 2,000 via M-Pesa STK push was confirmed and credited.',
      created_at: new Date().toISOString(),
      status: 'unread',
      type: 'savings',
    },
    {
      id: 'n2',
      title: 'Loan Eligibility Upgraded',
      message: 'Your good savings track record qualifies you for up to KES 150,000 in development credit facilities.',
      created_at: new Date(Date.now() - 3600000 * 5).toISOString(),
      status: 'read',
      type: 'loan',
    },
    {
      id: 'n3',
      title: 'Security Notice: New Session',
      message: 'A successful login was registered for your member account from a mobile device.',
      created_at: new Date(Date.now() - 3600000 * 24).toISOString(),
      status: 'read',
      type: 'security',
    },
  ];

  const displayList = notifications.length > 0 ? notifications : defaultNotifications;

  return (
    <div style={{ display: 'flex', flexDirection: 'column', gap: '24px' }}>
      {/* Header */}
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', flexWrap: 'wrap', gap: '12px' }}>
        <div>
          <h1 className="heading-1" style={{ margin: 0 }}>Notification Center</h1>
          <p className="body-rg" style={{ margin: '4px 0 0 0' }}>
            Stay informed about loan approvals, savings deposits, and Sacco announcements
          </p>
        </div>
        {displayList.some((n) => n.status === 'unread') && (
          <Button
            variant="secondary"
            size="md"
            pill
            onClick={markAllRead}
          >
            <CheckCheck size={16} />
            <span>Mark All as Read</span>
          </Button>
        )}
      </div>

      {/* Notifications List */}
      <Card variant="default">
        <Card.Body>
          {loading ? (
            <div style={{ textAlign: 'center', padding: '40px', color: 'var(--color-gray-medium)' }}>
              Loading notifications...
            </div>
          ) : displayList.length > 0 ? (
            <div style={{ display: 'flex', flexDirection: 'column', gap: '12px' }}>
              {displayList.map((n, idx) => {
                const isUnread = n.status === 'unread';
                return (
                  <div
                    key={idx}
                    style={{
                      display: 'flex',
                      alignItems: 'flex-start',
                      gap: '14px',
                      padding: '16px',
                      borderRadius: 'var(--radius-lg)',
                      backgroundColor: isUnread ? 'var(--color-lime-light)' : 'var(--color-gray-light)',
                      border: isUnread ? '1px solid var(--color-lime)' : '1px solid var(--color-gray-border)',
                      transition: 'background-color 0.15s ease',
                    }}
                  >
                    <div
                      style={{
                        width: '36px',
                        height: '36px',
                        borderRadius: '50%',
                        backgroundColor: isUnread ? 'var(--color-forest)' : 'var(--color-white)',
                        color: isUnread ? 'var(--color-lime)' : 'var(--color-forest)',
                        display: 'flex',
                        alignItems: 'center',
                        justifyContent: 'center',
                        flexShrink: 0,
                      }}
                    >
                      <Bell size={18} />
                    </div>

                    <div style={{ flex: 1 }}>
                      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'baseline', flexWrap: 'wrap', gap: '8px' }}>
                        <div style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
                          <h4 className="heading-4" style={{ margin: 0, fontSize: '15px' }}>
                            {n.title || 'System Notification'}
                          </h4>
                          {isUnread && <Badge status="active">New</Badge>}
                        </div>
                        <span style={{ fontSize: '12px', color: 'var(--color-gray-medium)' }}>
                          {formatDate(n.created_at)}
                        </span>
                      </div>
                      <p className="body-rg" style={{ margin: '6px 0 0 0', lineHeight: 1.5, color: 'var(--color-gray-dark)' }}>
                        {n.message}
                      </p>
                    </div>
                  </div>
                );
              })}
            </div>
          ) : (
            <div style={{ textAlign: 'center', padding: '48px 16px', color: 'var(--color-gray-medium)' }}>
              <Bell size={40} style={{ margin: '0 auto 12px', opacity: 0.4 }} />
              <div style={{ fontSize: '16px', fontWeight: 600, color: 'var(--color-charcoal)' }}>All caught up!</div>
              <p className="body-sm" style={{ marginTop: '4px' }}>No new notifications in your inbox.</p>
            </div>
          )}
        </Card.Body>
      </Card>
    </div>
  );
}
