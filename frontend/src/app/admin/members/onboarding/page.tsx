'use client';

import React, { useState } from 'react';
import { useRouter } from 'next/navigation';
import { api } from '@/lib/api';
import { useToast } from '@/context/ToastContext';
import { UserPlus, ArrowLeft, ArrowRight, ShieldCheck, CheckCircle2 } from 'lucide-react';

export default function AdminMemberOnboardingPage() {
  const router = useRouter();
  const { toast } = useToast();

  const [formData, setFormData] = useState({
    full_name: '',
    national_id: '',
    phone: '',
    email: '',
    gender: 'male',
    dob: '',
    occupation: 'Commercial Driver',
    address: 'Nairobi',
    next_of_kin_name: '',
    next_of_kin_phone: '',
    password: 'Password123',
  });

  const [loading, setLoading] = useState(false);

  const handleChange = (e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement>) => {
    setFormData({ ...formData, [e.target.name]: e.target.value });
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setLoading(true);

    try {
      const res = await api.post('/admin/onboard_member', formData);
      toast.success(`Member registered successfully! Assigned Reg No: ${res.data?.reg_no}`);
      router.push('/admin/members');
    } catch (err: any) {
      toast.error(err.message || 'Member onboarding failed.');
    } finally {
      setLoading(false);
    }
  };

  return (
    <div style={{ maxWidth: '800px', margin: '0 auto', display: 'flex', flexDirection: 'column', gap: '28px' }}>
      <div>
        <button onClick={() => router.back()} className="btn btn-sm btn-ghost" style={{ marginBottom: '8px' }}>
          <ArrowLeft size={16} /> Back
        </button>
        <h1 style={{ fontSize: '1.75rem', fontWeight: 800, color: 'var(--text-main)' }}>Member Onboarding</h1>
        <p style={{ color: 'var(--text-muted)', fontSize: '0.88rem' }}>Register and activate a new member account with verified standing</p>
      </div>

      <div className="card" style={{ padding: '36px' }}>
        <form onSubmit={handleSubmit} style={{ display: 'flex', flexDirection: 'column', gap: '20px' }}>
          <h3 style={{ fontSize: '1.05rem', fontWeight: 700, color: 'var(--brand-forest)', borderBottom: '1px solid var(--border-color)', paddingBottom: '8px' }}>
            Personal Identification
          </h3>
          <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(240px, 1fr))', gap: '16px' }}>
            <div>
              <label className="input-label">Full Name *</label>
              <input type="text" name="full_name" className="input-control" value={formData.full_name} onChange={handleChange} required />
            </div>
            <div>
              <label className="input-label">National ID / Passport *</label>
              <input type="text" name="national_id" className="input-control" value={formData.national_id} onChange={handleChange} required />
            </div>
            <div>
              <label className="input-label">Phone Number *</label>
              <input type="text" name="phone" className="input-control" value={formData.phone} onChange={handleChange} required />
            </div>
            <div>
              <label className="input-label">Email Address</label>
              <input type="email" name="email" className="input-control" value={formData.email} onChange={handleChange} />
            </div>
            <div>
              <label className="input-label">Gender</label>
              <select name="gender" className="input-control" value={formData.gender} onChange={handleChange}>
                <option value="male">Male</option>
                <option value="female">Female</option>
              </select>
            </div>
            <div>
              <label className="input-label">Date of Birth</label>
              <input type="date" name="dob" className="input-control" value={formData.dob} onChange={handleChange} />
            </div>
          </div>

          <h3 style={{ fontSize: '1.05rem', fontWeight: 700, color: 'var(--brand-forest)', borderBottom: '1px solid var(--border-color)', paddingBottom: '8px', marginTop: '10px' }}>
            Occupation & Emergency Contacts
          </h3>
          <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(240px, 1fr))', gap: '16px' }}>
            <div>
              <label className="input-label">Occupation / Route</label>
              <input type="text" name="occupation" className="input-control" value={formData.occupation} onChange={handleChange} />
            </div>
            <div>
              <label className="input-label">Physical Address</label>
              <input type="text" name="address" className="input-control" value={formData.address} onChange={handleChange} />
            </div>
            <div>
              <label className="input-label">Next of Kin Full Name</label>
              <input type="text" name="next_of_kin_name" className="input-control" value={formData.next_of_kin_name} onChange={handleChange} />
            </div>
            <div>
              <label className="input-label">Next of Kin Phone Number</label>
              <input type="text" name="next_of_kin_phone" className="input-control" value={formData.next_of_kin_phone} onChange={handleChange} />
            </div>
          </div>

          <button type="submit" disabled={loading} className="btn btn-lime btn-lg" style={{ marginTop: '10px' }}>
            {loading ? 'Registering...' : 'Onboard & Activate Member Account'} <ArrowRight size={18} />
          </button>
        </form>
      </div>
    </div>
  );
}
