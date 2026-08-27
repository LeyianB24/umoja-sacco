'use client';

import React, { useState } from 'react';
import { Navbar } from '@/components/layout/Navbar';
import { Footer } from '@/components/layout/Footer';
import { api } from '@/lib/api';
import { useToast } from '@/context/ToastContext';
import { Mail, Phone, MapPin, Send, CheckCircle2 } from 'lucide-react';

export default function ContactPage() {
  const { toast } = useToast();
  const [name, setName] = useState('');
  const [email, setEmail] = useState('');
  const [subject, setSubject] = useState('');
  const [message, setMessage] = useState('');
  const [loading, setLoading] = useState(false);
  const [sent, setSent] = useState(false);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!name || !email || !message) return;

    setLoading(true);
    try {
      await api.post('/public/contact', { name, email, subject, message });
      setSent(true);
      toast.success('Your message has been sent to our customer care team.');
    } catch (err: any) {
      toast.error(err.message || 'Failed to submit message.');
    } finally {
      setLoading(false);
    }
  };

  return (
    <div style={{ minHeight: '100vh', display: 'flex', flexDirection: 'column' }}>
      <Navbar />
      <main style={{ flex: 1, maxWidth: '1100px', margin: '0 auto', padding: '60px 24px 80px' }}>
        <div style={{ textAlign: 'center', marginBottom: '50px' }}>
          <span className="eyebrow-pill" style={{ marginBottom: '12px' }}>Get In Touch</span>
          <h1 style={{ fontSize: '2.4rem', fontWeight: 800, color: 'var(--text-main)' }}>
            Contact & Support Center
          </h1>
          <p style={{ color: 'var(--text-muted)', fontSize: '1rem', marginTop: '6px' }}>
            We are here to assist you with inquiries, membership questions, and technical support.
          </p>
        </div>

        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(280px, 1fr))', gap: '32px' }}>
          {/* Info Side */}
          <div style={{ display: 'flex', flexDirection: 'column', gap: '24px' }}>
            <div className="card">
              <div style={{ display: 'flex', gap: '16px', alignItems: 'flex-start' }}>
                <div style={{ width: '44px', height: '44px', borderRadius: '12px', backgroundColor: 'var(--brand-lime-soft)', color: 'var(--brand-forest)', display: 'flex', alignItems: 'center', justifyContent: 'center', flexShrink: 0 }}>
                  <MapPin size={22} />
                </div>
                <div>
                  <h3 style={{ fontSize: '1.05rem', fontWeight: 700, marginBottom: '4px' }}>Headquarters</h3>
                  <p style={{ color: 'var(--text-muted)', fontSize: '0.9rem', lineHeight: 1.6 }}>
                    Umoja Sacco Plaza, 4th Floor<br />
                    Commercial Avenue, Nairobi, Kenya
                  </p>
                </div>
              </div>
            </div>

            <div className="card">
              <div style={{ display: 'flex', gap: '16px', alignItems: 'flex-start' }}>
                <div style={{ width: '44px', height: '44px', borderRadius: '12px', backgroundColor: 'var(--brand-lime-soft)', color: 'var(--brand-forest)', display: 'flex', alignItems: 'center', justifyContent: 'center', flexShrink: 0 }}>
                  <Phone size={22} />
                </div>
                <div>
                  <h3 style={{ fontSize: '1.05rem', fontWeight: 700, marginBottom: '4px' }}>Helpline Numbers</h3>
                  <p style={{ color: 'var(--text-muted)', fontSize: '0.9rem', lineHeight: 1.6 }}>
                    Direct: +254 700 000 000<br />
                    Toll Free: 0800 000 000 (Mon - Sat, 7am - 8pm)
                  </p>
                </div>
              </div>
            </div>

            <div className="card">
              <div style={{ display: 'flex', gap: '16px', alignItems: 'flex-start' }}>
                <div style={{ width: '44px', height: '44px', borderRadius: '12px', backgroundColor: 'var(--brand-lime-soft)', color: 'var(--brand-forest)', display: 'flex', alignItems: 'center', justifyContent: 'center', flexShrink: 0 }}>
                  <Mail size={22} />
                </div>
                <div>
                  <h3 style={{ fontSize: '1.05rem', fontWeight: 700, marginBottom: '4px' }}>Email Support</h3>
                  <p style={{ color: 'var(--text-muted)', fontSize: '0.9rem', lineHeight: 1.6 }}>
                    General: info@umojasacco.co.ke<br />
                    Support: support@umojasacco.co.ke
                  </p>
                </div>
              </div>
            </div>
          </div>

          {/* Form Side */}
          <div className="card" style={{ padding: '36px' }}>
            <h3 style={{ fontSize: '1.3rem', fontWeight: 800, marginBottom: '6px' }}>Send Us a Message</h3>
            <p style={{ color: 'var(--text-muted)', fontSize: '0.88rem', marginBottom: '24px' }}>
              Fill out the form below and a representative will respond within 24 hours.
            </p>

            {sent ? (
              <div style={{ textAlign: 'center', padding: '30px 0' }}>
                <CheckCircle2 size={48} color="#16a34a" style={{ margin: '0 auto 16px' }} />
                <h4 style={{ fontSize: '1.1rem', fontWeight: 700, marginBottom: '8px' }}>Message Received!</h4>
                <p style={{ color: 'var(--text-muted)', fontSize: '0.9rem', marginBottom: '20px' }}>
                  Thank you for contacting us. We will get back to you shortly.
                </p>
                <button onClick={() => setSent(false)} className="btn btn-outline-forest">
                  Send Another Message
                </button>
              </div>
            ) : (
              <form onSubmit={handleSubmit} style={{ display: 'flex', flexDirection: 'column', gap: '16px' }}>
                <div>
                  <label className="input-label">Your Name</label>
                  <input
                    type="text"
                    className="input-control"
                    placeholder="e.g. John Kamau"
                    value={name}
                    onChange={(e) => setName(e.target.value)}
                    required
                  />
                </div>
                <div>
                  <label className="input-label">Email Address</label>
                  <input
                    type="email"
                    className="input-control"
                    placeholder="e.g. john@example.com"
                    value={email}
                    onChange={(e) => setEmail(e.target.value)}
                    required
                  />
                </div>
                <div>
                  <label className="input-label">Subject</label>
                  <input
                    type="text"
                    className="input-control"
                    placeholder="e.g. Loan Application Inquiry"
                    value={subject}
                    onChange={(e) => setSubject(e.target.value)}
                  />
                </div>
                <div>
                  <label className="input-label">Message</label>
                  <textarea
                    className="input-control"
                    rows={4}
                    placeholder="How can we assist you today?"
                    value={message}
                    onChange={(e) => setMessage(e.target.value)}
                    required
                  />
                </div>
                <button type="submit" disabled={loading} className="btn btn-lime btn-lg" style={{ marginTop: '8px' }}>
                  {loading ? 'Sending Message...' : 'Send Message'} <Send size={16} />
                </button>
              </form>
            )}
          </div>
        </div>
      </main>
      <Footer />
    </div>
  );
}
