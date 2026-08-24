'use client';

import React, { useEffect, useState } from 'react';
import { api } from '@/lib/api';
import { useToast } from '@/context/ToastContext';
import { Settings, Save, ShieldCheck } from 'lucide-react';

export default function AdminSettingsPage() {
  const { toast } = useToast();
  const [settings, setSettings] = useState<any>({
    sacco_name: 'Umoja Drivers Sacco',
    currency: 'KES',
    default_interest_rate: '10.0',
    share_unit_price: '100.00',
    registration_fee: '1000.00',
    min_monthly_savings: '1000.00',
    max_loan_multiplier: '3.0',
  });
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);

  useEffect(() => {
    const fetchSettings = async () => {
      try {
        const res = await api.get('/admin/settings');
        if (res.status === 'success' && res.data.settings) {
          setSettings(res.data.settings);
        }
      } catch (err) {
        console.error(err);
      } finally {
        setLoading(false);
      }
    };
    fetchSettings();
  }, []);

  const handleChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    setSettings({ ...settings, [e.target.name]: e.target.value });
  };

  const handleSave = async (e: React.FormEvent) => {
    e.preventDefault();
    setSaving(true);
    try {
      await api.post('/admin/update_settings', { settings });
      toast.success('System configuration settings saved.');
    } catch (err: any) {
      toast.error(err.message || 'Failed to update settings.');
    } finally {
      setSaving(false);
    }
  };

  return (
    <div style={{ maxWidth: '800px', margin: '0 auto', display: 'flex', flexDirection: 'column', gap: '28px' }}>
      <div>
        <h1 style={{ fontSize: '1.75rem', fontWeight: 800, color: 'var(--text-main)' }}>Global System Settings</h1>
        <p style={{ color: 'var(--text-muted)', fontSize: '0.88rem' }}>Configure interest parameters, share unit pricing, and operational thresholds</p>
      </div>

      <div className="card" style={{ padding: '36px' }}>
        <form onSubmit={handleSave} style={{ display: 'flex', flexDirection: 'column', gap: '22px' }}>
          <h3 style={{ fontSize: '1.05rem', fontWeight: 700, color: 'var(--brand-forest)', borderBottom: '1px solid var(--border-color)', paddingBottom: '8px' }}>
            Core Sacco Parameters
          </h3>

          <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(240px, 1fr))', gap: '18px' }}>
            <div>
              <label className="input-label">Sacco Legal Entity Name</label>
              <input
                type="text"
                name="sacco_name"
                className="input-control"
                value={settings.sacco_name || ''}
                onChange={handleChange}
                required
              />
            </div>

            <div>
              <label className="input-label">Base Currency</label>
              <input
                type="text"
                name="currency"
                className="input-control"
                value={settings.currency || 'KES'}
                onChange={handleChange}
                required
              />
            </div>

            <div>
              <label className="input-label">Default Annual Loan Interest (%)</label>
              <input
                type="number"
                step="0.1"
                name="default_interest_rate"
                className="input-control"
                value={settings.default_interest_rate || '10.0'}
                onChange={handleChange}
                required
              />
            </div>

            <div>
              <label className="input-label">Share Unit Price (KES)</label>
              <input
                type="number"
                step="1"
                name="share_unit_price"
                className="input-control"
                value={settings.share_unit_price || '100.00'}
                onChange={handleChange}
                required
              />
            </div>

            <div>
              <label className="input-label">Minimum Monthly Savings (KES)</label>
              <input
                type="number"
                step="50"
                name="min_monthly_savings"
                className="input-control"
                value={settings.min_monthly_savings || '1000.00'}
                onChange={handleChange}
                required
              />
            </div>

            <div>
              <label className="input-label">Max Loan Multiplier (Savings x)</label>
              <input
                type="number"
                step="0.5"
                name="max_loan_multiplier"
                className="input-control"
                value={settings.max_loan_multiplier || '3.0'}
                onChange={handleChange}
                required
              />
            </div>
          </div>

          <button type="submit" disabled={saving} className="btn btn-lime btn-lg" style={{ marginTop: '10px' }}>
            <Save size={18} /> {saving ? 'Saving...' : 'Save Configuration Changes'}
          </button>
        </form>
      </div>
    </div>
  );
}
