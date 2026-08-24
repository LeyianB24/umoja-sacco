'use client';

import React, { useState, useEffect } from 'react';
import Link from 'next/link';
import { Navbar } from '@/components/layout/Navbar';
import { Footer } from '@/components/layout/Footer';
import {
  ArrowRight,
  PiggyBank,
  Banknote,
  HeartPulse,
  PieChart,
  ShieldCheck,
  Zap,
  Award,
  ChevronDown,
  Calculator,
  ChevronLeft,
  ChevronRight,
  Wallet2,
  Building2,
  TrendingUp,
  Bus,
  Sprout,
  Fuel,
  BookOpen,
} from 'lucide-react';
import { formatKES } from '@/lib/utils';

export default function LandingPage() {
  // Slideshow State (19 Sacco Asset Images)
  const totalSlides = 19;
  const [currentSlide, setCurrentSlide] = useState(0);

  useEffect(() => {
    const timer = setInterval(() => {
      setCurrentSlide((prev) => (prev + 1) % totalSlides);
    }, 4000);
    return () => clearInterval(timer);
  }, [totalSlides]);

  const prevSlide = () => {
    setCurrentSlide((prev) => (prev - 1 + totalSlides) % totalSlides);
  };

  const nextSlide = () => {
    setCurrentSlide((prev) => (prev + 1) % totalSlides);
  };

  // Loan Calculator State
  const [amount, setAmount] = useState<number>(100000);
  const [months, setMonths] = useState<number>(12);
  const interestRate = 0.10; // 10% per annum

  const totalInterest = amount * (interestRate * (months / 12));
  const totalRepayable = amount + totalInterest;
  const monthlyInstallment = totalRepayable / months;

  // FAQ Accordion State
  const [openFaq, setOpenFaq] = useState<number | null>(0);

  const faqs = [
    {
      q: 'Who is eligible to join Umoja Sacco?',
      a: 'Membership is open to commercial drivers, public transport operators, courier and delivery personnel, fleet owners, and allied transport professionals across Kenya.',
    },
    {
      q: 'How fast can I get a loan approved?',
      a: 'Emergency and Instant Mobile loans are approved and disbursed via M-Pesa within 15 minutes. Development and asset financing loans are processed within 24 to 48 hours.',
    },
    {
      q: 'What are the minimum monthly savings required?',
      a: 'The minimum monthly savings contribution is KES 1,000, which earns compounding annual interest and qualifies you for up to 3x your savings in loan credit.',
    },
    {
      q: 'How does the Welfare & Solidarity Fund protect members?',
      a: 'The Welfare Fund provides immediate cash relief for hospitalization, bereavement, road emergency assistance, and legal aid without touching your core savings.',
    },
  ];

  return (
    <div style={{ minHeight: '100vh', display: 'flex', flexDirection: 'column', backgroundColor: 'var(--bg-primary)' }}>
      <Navbar />

      <main style={{ flex: 1 }}>
        {/* ═════════════════════════════════════════════════════════════════════
            HERO SECTION WITH 3D POKER SLIDESHOW
        ═════════════════════════════════════════════════════════════════════ */}
        <section
          style={{
            position: 'relative',
            background: `linear-gradient(150deg, rgba(11, 30, 22, 0.94) 0%, rgba(15, 57, 43, 0.88) 45%, rgba(7, 20, 15, 0.96) 100%), url('/assets/images/sacco3.jpg') center/cover no-repeat`,
            color: '#FFFFFF',
            padding: '90px 24px 110px',
            overflow: 'hidden',
          }}
        >
          {/* Ambient Glows */}
          <div
            style={{
              position: 'absolute',
              top: '-120px',
              right: '-80px',
              width: '500px',
              height: '500px',
              background: 'radial-gradient(circle, rgba(208, 247, 100, 0.14) 0%, transparent 65%)',
              borderRadius: '50%',
              pointerEvents: 'none',
            }}
          />
          <div
            style={{
              position: 'absolute',
              bottom: '-100px',
              left: '-60px',
              width: '360px',
              height: '360px',
              background: 'radial-gradient(circle, rgba(208, 247, 100, 0.08) 0%, transparent 65%)',
              borderRadius: '50%',
              pointerEvents: 'none',
            }}
          />

          <div
            style={{
              maxWidth: '1240px',
              margin: '0 auto',
              display: 'grid',
              gridTemplateColumns: 'repeat(auto-fit, minmax(320px, 1fr))',
              gap: '50px',
              alignItems: 'center',
              position: 'relative',
              zIndex: 3,
            }}
          >
            {/* Left Column Text */}
            <div>
              <div className="eyebrow-pill" style={{ marginBottom: '20px' }}>
                <span className="eyebrow-dot" /> Est. Umoja Drivers Sacco Ltd. • SASRA Regulated
              </div>

              <h1
                style={{
                  fontSize: 'clamp(2.4rem, 5vw, 3.8rem)',
                  fontWeight: 800,
                  lineHeight: 1.1,
                  letterSpacing: '-1.5px',
                  marginBottom: '20px',
                }}
              >
                Financial Freedom <br />
                <span style={{ color: 'var(--brand-lime)' }}>Starts Here.</span>
              </h1>

              <p
                style={{
                  fontSize: '1.05rem',
                  lineHeight: 1.7,
                  color: 'rgba(255, 255, 255, 0.8)',
                  marginBottom: '36px',
                  maxWidth: '520px',
                }}
              >
                Umoja Sacco is the financial backbone for the transport community — owning <b style={{ color: 'var(--brand-gold)' }}>fleets, real estate, and agribusiness</b> and delivering generational wealth to every member.
              </p>

              <div style={{ display: 'flex', flexWrap: 'wrap', gap: '14px', marginBottom: '40px' }}>
                <Link href="/register" className="btn btn-lime btn-lg">
                  Join Sacco Today <ArrowRight size={18} />
                </Link>
                <Link href="/login" className="btn btn-outline-lime btn-lg">
                  Member Portal Login
                </Link>
              </div>

              {/* Trust Indicators */}
              <div
                style={{
                  display: 'flex',
                  alignItems: 'center',
                  gap: '24px',
                  borderTop: '1px solid rgba(255, 255, 255, 0.15)',
                  paddingTop: '24px',
                  flexWrap: 'wrap',
                }}
              >
                <div>
                  <div style={{ fontSize: '1.3rem', fontWeight: 800, color: 'var(--brand-lime)', lineHeight: 1 }}>14.5%</div>
                  <div style={{ fontSize: '0.68rem', fontWeight: 700, color: 'rgba(255,255,255,0.6)', textTransform: 'uppercase', marginTop: '4px' }}>Avg. Dividend</div>
                </div>
                <div style={{ width: '1px', height: '28px', backgroundColor: 'rgba(255,255,255,0.15)' }} />
                <div>
                  <div style={{ fontSize: '1.3rem', fontWeight: 800, color: 'var(--brand-lime)', lineHeight: 1 }}>15 Mins</div>
                  <div style={{ fontSize: '0.68rem', fontWeight: 700, color: 'rgba(255,255,255,0.6)', textTransform: 'uppercase', marginTop: '4px' }}>Loan Payout</div>
                </div>
                <div style={{ width: '1px', height: '28px', backgroundColor: 'rgba(255,255,255,0.15)' }} />
                <div>
                  <div style={{ fontSize: '1.3rem', fontWeight: 800, color: 'var(--brand-lime)', lineHeight: 1 }}>KES 650M+</div>
                  <div style={{ fontSize: '0.68rem', fontWeight: 700, color: 'rgba(255,255,255,0.6)', textTransform: 'uppercase', marginTop: '4px' }}>Asset Base</div>
                </div>
              </div>
            </div>

            {/* Right Column: 3D Poker Card Interactive Slideshow */}
            <div style={{ display: 'flex', flexDirection: 'column', alignItems: 'center', justifyContent: 'center' }}>
              <div
                style={{
                  perspective: '1200px',
                  width: '100%',
                  maxWidth: '380px',
                  height: '400px',
                  position: 'relative',
                  display: 'flex',
                  alignItems: 'center',
                  justifyContent: 'center',
                }}
              >
                {Array.from({ length: totalSlides }).map((_, idx) => {
                  const imageNum = idx + 1;
                  const isActive = idx === currentSlide;
                  const isLeft = idx === (currentSlide - 1 + totalSlides) % totalSlides;
                  const isRight = idx === (currentSlide + 1) % totalSlides;

                  let transform = 'translateZ(-200px) scale(0.8)';
                  let opacity = 0;
                  let zIndex = 1;
                  let pointerEvents = 'none';

                  if (isActive) {
                    transform = 'rotateY(0deg) translateZ(0) scale(1.05)';
                    opacity = 1;
                    zIndex = 10;
                    pointerEvents = 'auto';
                  } else if (isLeft) {
                    transform = 'rotateY(20deg) translateX(-140px) translateZ(-80px) scale(0.88)';
                    opacity = 0.6;
                    zIndex = 5;
                  } else if (isRight) {
                    transform = 'rotateY(-20deg) translateX(140px) translateZ(-80px) scale(0.88)';
                    opacity = 0.6;
                    zIndex = 5;
                  }

                  return (
                    <div
                      key={idx}
                      onClick={() => setCurrentSlide(idx)}
                      style={{
                        position: 'absolute',
                        width: '270px',
                        height: '370px',
                        borderRadius: '24px',
                        backgroundColor: '#FFFFFF',
                        border: '5px solid #FFFFFF',
                        boxShadow: isActive ? '0 24px 60px rgba(0,0,0,0.45)' : '0 12px 30px rgba(0,0,0,0.25)',
                        transition: 'all 0.55s cubic-bezier(0.23, 1, 0.32, 1)',
                        transform,
                        opacity,
                        zIndex,
                        cursor: 'pointer',
                        overflow: 'hidden',
                      }}
                    >
                      <img
                        src={`/assets/images/sacco${imageNum}.jpg`}
                        alt={`Sacco Asset ${imageNum}`}
                        style={{
                          width: '100%',
                          height: '100%',
                          objectFit: 'cover',
                          borderRadius: '19px',
                          display: 'block',
                        }}
                      />
                      {isActive && (
                        <div
                          style={{
                            position: 'absolute',
                            bottom: 0,
                            left: 0,
                            right: 0,
                            padding: '16px',
                            background: 'linear-gradient(to top, rgba(0,0,0,0.8) 0%, transparent 100%)',
                            color: '#FFFFFF',
                          }}
                        >
                          <div style={{ fontSize: '0.75rem', fontWeight: 800, color: 'var(--brand-lime)', textTransform: 'uppercase' }}>
                            Sacco Fleet & Asset #{imageNum}
                          </div>
                        </div>
                      )}
                    </div>
                  );
                })}
              </div>

              {/* Slideshow Controls */}
              <div style={{ display: 'flex', gap: '14px', marginTop: '20px', zIndex: 20 }}>
                <button
                  onClick={prevSlide}
                  className="ctrl-btn"
                  style={{
                    width: '42px',
                    height: '42px',
                    borderRadius: '12px',
                    backgroundColor: 'rgba(208, 247, 100, 0.12)',
                    border: '1px solid rgba(208, 247, 100, 0.3)',
                    color: 'var(--brand-lime)',
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'center',
                    cursor: 'pointer',
                    transition: 'all 0.2s',
                  }}
                >
                  <ChevronLeft size={20} />
                </button>
                <button
                  onClick={nextSlide}
                  className="ctrl-btn"
                  style={{
                    width: '42px',
                    height: '42px',
                    borderRadius: '12px',
                    backgroundColor: 'rgba(208, 247, 100, 0.12)',
                    border: '1px solid rgba(208, 247, 100, 0.3)',
                    color: 'var(--brand-lime)',
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'center',
                    cursor: 'pointer',
                    transition: 'all 0.2s',
                  }}
                >
                  <ChevronRight size={20} />
                </button>
              </div>
            </div>
          </div>
        </section>

        {/* ═════════════════════════════════════════════════════════════════════
            STATS RIBBON
        ═════════════════════════════════════════════════════════════════════ */}
        <section
          style={{
            background: 'linear-gradient(135deg, #0F392B 0%, #1a5c43 100%)',
            padding: '36px 0',
            color: '#FFFFFF',
            borderBottom: '1px solid rgba(208, 247, 100, 0.15)',
          }}
        >
          <div
            style={{
              maxWidth: '1160px',
              margin: '0 auto',
              padding: '0 24px',
              display: 'grid',
              gridTemplateColumns: 'repeat(auto-fit, minmax(200px, 1fr))',
              gap: '24px',
              textAlign: 'center',
            }}
          >
            <div>
              <div style={{ fontSize: '2rem', fontWeight: 800, color: 'var(--brand-lime)', lineHeight: 1 }}>14.5%</div>
              <div style={{ fontSize: '0.72rem', fontWeight: 700, color: 'rgba(255,255,255,0.6)', textTransform: 'uppercase', marginTop: '6px' }}>Avg. Dividend Rate</div>
            </div>
            <div>
              <div style={{ fontSize: '2rem', fontWeight: 800, color: 'var(--brand-lime)', lineHeight: 1 }}>KES 650M+</div>
              <div style={{ fontSize: '0.72rem', fontWeight: 700, color: 'rgba(255,255,255,0.6)', textTransform: 'uppercase', marginTop: '6px' }}>Asset Base Goal</div>
            </div>
            <div>
              <div style={{ fontSize: '2rem', fontWeight: 800, color: 'var(--brand-lime)', lineHeight: 1 }}>24 - 48 hrs</div>
              <div style={{ fontSize: '0.72rem', fontWeight: 700, color: 'rgba(255,255,255,0.6)', textTransform: 'uppercase', marginTop: '6px' }}>Loan Processing</div>
            </div>
            <div>
              <div style={{ fontSize: '2rem', fontWeight: 800, color: 'var(--brand-lime)', lineHeight: 1 }}>24 / 7</div>
              <div style={{ fontSize: '0.72rem', fontWeight: 700, color: 'rgba(255,255,255,0.6)', textTransform: 'uppercase', marginTop: '6px' }}>Digital Member Access</div>
            </div>
          </div>
        </section>

        {/* ═════════════════════════════════════════════════════════════════════
            THE UMOJA BLUEPRINT SECTION
        ═════════════════════════════════════════════════════════════════════ */}
        <section id="wealth-model" style={{ padding: '88px 24px', maxWidth: '1240px', margin: '0 auto' }}>
          <div style={{ textAlign: 'center', marginBottom: '56px' }}>
            <span className="eyebrow-pill" style={{ marginBottom: '14px' }}>The Umoja Blueprint</span>
            <h2 style={{ fontSize: '2.4rem', fontWeight: 800, color: 'var(--text-main)', letterSpacing: '-0.5px' }}>
              How Your Money Grows With Us
            </h2>
            <p style={{ color: 'var(--text-muted)', fontSize: '1rem', maxWidth: '600px', margin: '12px auto 0' }}>
              A proven four-step investment cycle that turns monthly contributions into lasting generational wealth.
            </p>
          </div>

          <div
            style={{
              display: 'grid',
              gridTemplateColumns: 'repeat(auto-fit, minmax(260px, 1fr))',
              gap: '24px',
            }}
          >
            {/* Step 1 */}
            <div className="card card-hover" style={{ textAlign: 'center', padding: '32px 24px' }}>
              <div
                style={{
                  width: '42px',
                  height: '42px',
                  borderRadius: '12px',
                  backgroundColor: 'var(--brand-forest)',
                  color: 'var(--brand-lime)',
                  fontSize: '0.9rem',
                  fontWeight: 800,
                  display: 'flex',
                  alignItems: 'center',
                  justifyContent: 'center',
                  margin: '0 auto 18px',
                }}
              >
                1
              </div>
              <Wallet2 size={36} color="var(--brand-forest)" style={{ margin: '0 auto 12px' }} />
              <h3 style={{ fontSize: '1.1rem', fontWeight: 800, marginBottom: '8px' }}>1. Mobilization</h3>
              <p style={{ color: 'var(--text-muted)', fontSize: '0.88rem', lineHeight: 1.6 }}>
                Members contribute monthly deposits and share capital, forming a strong consolidated fund base.
              </p>
            </div>

            {/* Step 2 */}
            <div className="card card-hover" style={{ textAlign: 'center', padding: '32px 24px' }}>
              <div
                style={{
                  width: '42px',
                  height: '42px',
                  borderRadius: '12px',
                  backgroundColor: 'var(--brand-forest)',
                  color: 'var(--brand-lime)',
                  fontSize: '0.9rem',
                  fontWeight: 800,
                  display: 'flex',
                  alignItems: 'center',
                  justifyContent: 'center',
                  margin: '0 auto 18px',
                }}
              >
                2
              </div>
              <Building2 size={36} color="var(--brand-forest)" style={{ margin: '0 auto 12px' }} />
              <h3 style={{ fontSize: '1.1rem', fontWeight: 800, marginBottom: '8px' }}>2. Investment</h3>
              <p style={{ color: 'var(--text-muted)', fontSize: '0.88rem', lineHeight: 1.6 }}>
                Funds are deployed into high-yield assets: fleets, commercial real estate, and agribusiness.
              </p>
            </div>

            {/* Step 3 */}
            <div className="card card-hover" style={{ textAlign: 'center', padding: '32px 24px' }}>
              <div
                style={{
                  width: '42px',
                  height: '42px',
                  borderRadius: '12px',
                  backgroundColor: 'var(--brand-forest)',
                  color: 'var(--brand-lime)',
                  fontSize: '0.9rem',
                  fontWeight: 800,
                  display: 'flex',
                  alignItems: 'center',
                  justifyContent: 'center',
                  margin: '0 auto 18px',
                }}
              >
                3
              </div>
              <TrendingUp size={36} color="var(--brand-forest)" style={{ margin: '0 auto 12px' }} />
              <h3 style={{ fontSize: '1.1rem', fontWeight: 800, marginBottom: '8px' }}>3. Returns</h3>
              <p style={{ color: 'var(--text-muted)', fontSize: '0.88rem', lineHeight: 1.6 }}>
                Assets generate revenue daily through passenger fares, rental income, and loan interests.
              </p>
            </div>

            {/* Step 4 */}
            <div className="card card-hover" style={{ textAlign: 'center', padding: '32px 24px', borderColor: 'var(--brand-gold)' }}>
              <div
                style={{
                  width: '42px',
                  height: '42px',
                  borderRadius: '12px',
                  backgroundColor: 'var(--brand-gold)',
                  color: '#FFFFFF',
                  fontSize: '0.9rem',
                  fontWeight: 800,
                  display: 'flex',
                  alignItems: 'center',
                  justifyContent: 'center',
                  margin: '0 auto 18px',
                }}
              >
                4
              </div>
              <PieChart size={36} color="var(--brand-gold)" style={{ margin: '0 auto 12px' }} />
              <h3 style={{ fontSize: '1.1rem', fontWeight: 800, marginBottom: '8px' }}>4. Dividends</h3>
              <p style={{ color: 'var(--text-muted)', fontSize: '0.88rem', lineHeight: 1.6 }}>
                Profits are returned to members yearly as high-yield dividends and interest on savings.
              </p>
            </div>
          </div>
        </section>

        {/* ═════════════════════════════════════════════════════════════════════
            DIVERSIFIED ASSET PORTFOLIO
        ═════════════════════════════════════════════ */}
        <section id="portfolio" style={{ backgroundColor: 'var(--surface-2)', padding: '88px 24px', borderTop: '1px solid var(--border-color)', borderBottom: '1px solid var(--border-color)' }}>
          <div style={{ maxWidth: '1240px', margin: '0 auto' }}>
            <div style={{ textAlign: 'center', marginBottom: '56px' }}>
              <span className="eyebrow-pill" style={{ marginBottom: '14px' }}>Diversified Assets</span>
              <h2 style={{ fontSize: '2.4rem', fontWeight: 800, color: 'var(--text-main)' }}>
                Collective Investments That Deliver
              </h2>
              <p style={{ color: 'var(--text-muted)', fontSize: '1rem', maxWidth: '600px', margin: '12px auto 0' }}>
                Every shilling you contribute is deployed into real, revenue-producing physical assets.
              </p>
            </div>

            <div
              style={{
                display: 'grid',
                gridTemplateColumns: 'repeat(auto-fit, minmax(260px, 1fr))',
                gap: '24px',
              }}
            >
              <div className="card card-hover" style={{ textAlign: 'center', padding: '32px 20px' }}>
                <div style={{ width: '56px', height: '56px', borderRadius: '16px', background: 'linear-gradient(135deg, #0F392B, #1a5c43)', color: 'var(--brand-lime)', display: 'flex', alignItems: 'center', justifyContent: 'center', margin: '0 auto 18px' }}>
                  <Building2 size={28} />
                </div>
                <h3 style={{ fontSize: '1.05rem', fontWeight: 800, marginBottom: '8px' }}>Commercial Real Estate</h3>
                <p style={{ color: 'var(--text-muted)', fontSize: '0.85rem', lineHeight: 1.6 }}>
                  Modern rental commercial units and prime plots generating passive monthly income for the society.
                </p>
              </div>

              <div className="card card-hover" style={{ textAlign: 'center', padding: '32px 20px' }}>
                <div style={{ width: '56px', height: '56px', borderRadius: '16px', background: 'linear-gradient(135deg, #0F392B, #1a5c43)', color: 'var(--brand-lime)', display: 'flex', alignItems: 'center', justifyContent: 'center', margin: '0 auto 18px' }}>
                  <Bus size={28} />
                </div>
                <h3 style={{ fontSize: '1.05rem', fontWeight: 800, marginBottom: '8px' }}>Matatu Fleet Ownership</h3>
                <p style={{ color: 'var(--text-muted)', fontSize: '0.85rem', lineHeight: 1.6 }}>
                  A modern, highly profitable fleet operating on the region's highest-demand commercial passenger routes.
                </p>
              </div>

              <div className="card card-hover" style={{ textAlign: 'center', padding: '32px 20px' }}>
                <div style={{ width: '56px', height: '56px', borderRadius: '16px', background: 'linear-gradient(135deg, #0F392B, #1a5c43)', color: 'var(--brand-lime)', display: 'flex', alignItems: 'center', justifyContent: 'center', margin: '0 auto 18px' }}>
                  <Sprout size={28} />
                </div>
                <h3 style={{ fontSize: '1.05rem', fontWeight: 800, marginBottom: '8px' }}>Agribusiness Operations</h3>
                <p style={{ color: 'var(--text-muted)', fontSize: '0.85rem', lineHeight: 1.6 }}>
                  Strategic agricultural ventures, commercial horticulture, and food security value chain investments.
                </p>
              </div>

              <div className="card card-hover" style={{ textAlign: 'center', padding: '32px 20px' }}>
                <div style={{ width: '56px', height: '56px', borderRadius: '16px', background: 'linear-gradient(135deg, #0F392B, #1a5c43)', color: 'var(--brand-lime)', display: 'flex', alignItems: 'center', justifyContent: 'center', margin: '0 auto 18px' }}>
                  <Fuel size={28} />
                </div>
                <h3 style={{ fontSize: '1.05rem', fontWeight: 800, marginBottom: '8px' }}>Fueling Stations</h3>
                <p style={{ color: 'var(--text-muted)', fontSize: '0.85rem', lineHeight: 1.6 }}>
                  High-traffic fueling outlets offering discounted fuel to members and reliable daily cash revenue.
                </p>
              </div>
            </div>
          </div>
        </section>

        {/* ═════════════════════════════════════════════════════════════════════
            INTERACTIVE LOAN CALCULATOR
        ═════════════════════════════════════════════ */}
        <section id="calculator" style={{ padding: '88px 24px', maxWidth: '1080px', margin: '0 auto' }}>
          <div style={{ textAlign: 'center', marginBottom: '50px' }}>
            <span className="eyebrow-pill" style={{ marginBottom: '12px' }}>
              <Calculator size={14} /> Transparent Financing
            </span>
            <h2 style={{ fontSize: '2.2rem', fontWeight: 800, color: 'var(--text-main)' }}>
              Interactive Loan Repayment Calculator
            </h2>
            <p style={{ color: 'var(--text-muted)', fontSize: '1rem', marginTop: '8px' }}>
              Select your loan amount and repayment period to see instant monthly installments.
            </p>
          </div>

          <div
            style={{
              backgroundColor: 'var(--bg-surface)',
              borderRadius: '24px',
              border: '1px solid var(--border-color)',
              boxShadow: 'var(--shadow-lg)',
              padding: '40px',
              display: 'grid',
              gridTemplateColumns: 'repeat(auto-fit, minmax(300px, 1fr))',
              gap: '50px',
              alignItems: 'center',
            }}
          >
            {/* Sliders */}
            <div>
              <div style={{ marginBottom: '32px' }}>
                <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: '10px' }}>
                  <label className="input-label" style={{ margin: 0 }}>Loan Amount</label>
                  <span style={{ fontWeight: 800, color: 'var(--brand-forest)', fontSize: '1.2rem' }}>
                    {formatKES(amount)}
                  </span>
                </div>
                <input
                  type="range"
                  min={10000}
                  max={1000000}
                  step={10000}
                  value={amount}
                  onChange={(e) => setAmount(Number(e.target.value))}
                  style={{ width: '100%', accentColor: '#0F392B', cursor: 'pointer' }}
                />
                <div style={{ display: 'flex', justifyContent: 'space-between', fontSize: '0.75rem', color: 'var(--text-muted)', marginTop: '6px' }}>
                  <span>KES 10,000</span>
                  <span>KES 1,000,000</span>
                </div>
              </div>

              <div>
                <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: '10px' }}>
                  <label className="input-label" style={{ margin: 0 }}>Repayment Duration</label>
                  <span style={{ fontWeight: 800, color: 'var(--brand-forest)', fontSize: '1.2rem' }}>
                    {months} Months
                  </span>
                </div>
                <input
                  type="range"
                  min={1}
                  max={36}
                  step={1}
                  value={months}
                  onChange={(e) => setMonths(Number(e.target.value))}
                  style={{ width: '100%', accentColor: '#0F392B', cursor: 'pointer' }}
                />
                <div style={{ display: 'flex', justifyContent: 'space-between', fontSize: '0.75rem', color: 'var(--text-muted)', marginTop: '6px' }}>
                  <span>1 Month</span>
                  <span>36 Months</span>
                </div>
              </div>
            </div>

            {/* Breakdown Card */}
            <div
              style={{
                background: 'linear-gradient(135deg, #0B1E17 0%, #0F392B 100%)',
                color: '#FFFFFF',
                borderRadius: '20px',
                padding: '32px',
                boxShadow: 'var(--shadow-md)',
              }}
            >
              <div style={{ fontSize: '0.85rem', color: 'rgba(255,255,255,0.7)', marginBottom: '4px' }}>
                Estimated Monthly Payment
              </div>
              <div style={{ fontSize: '2.4rem', fontWeight: 800, color: 'var(--brand-lime)', letterSpacing: '-0.5px', marginBottom: '24px' }}>
                {formatKES(monthlyInstallment)}
              </div>

              <div style={{ display: 'flex', flexDirection: 'column', gap: '12px', borderTop: '1px solid rgba(255,255,255,0.15)', paddingTop: '20px', marginBottom: '28px' }}>
                <div style={{ display: 'flex', justifyContent: 'space-between', fontSize: '0.88rem' }}>
                  <span style={{ color: 'rgba(255,255,255,0.7)' }}>Principal Amount:</span>
                  <span style={{ fontWeight: 700 }}>{formatKES(amount)}</span>
                </div>
                <div style={{ display: 'flex', justifyContent: 'space-between', fontSize: '0.88rem' }}>
                  <span style={{ color: 'rgba(255,255,255,0.7)' }}>Total Interest (10% p.a.):</span>
                  <span style={{ fontWeight: 700, color: 'var(--brand-lime)' }}>{formatKES(totalInterest)}</span>
                </div>
                <div style={{ display: 'flex', justifyContent: 'space-between', fontSize: '0.88rem' }}>
                  <span style={{ color: 'rgba(255,255,255,0.7)' }}>Total Repayable:</span>
                  <span style={{ fontWeight: 800 }}>{formatKES(totalRepayable)}</span>
                </div>
              </div>

              <Link href="/register" className="btn btn-lime" style={{ width: '100%' }}>
                Apply for this Loan <ArrowRight size={16} />
              </Link>
            </div>
          </div>
        </section>

        {/* ═════════════════════════════════════════════════════════════════════
            FAQS ACCORDION
        ═════════════════════════════════════════════ */}
        <section style={{ padding: '80px 24px', maxWidth: '840px', margin: '0 auto' }}>
          <div style={{ textAlign: 'center', marginBottom: '48px' }}>
            <span className="eyebrow-pill" style={{ marginBottom: '12px' }}>Got Questions?</span>
            <h2 style={{ fontSize: '2.2rem', fontWeight: 800, color: 'var(--text-main)' }}>
              Frequently Asked Questions
            </h2>
          </div>

          <div style={{ display: 'flex', flexDirection: 'column', gap: '16px' }}>
            {faqs.map((faq, idx) => {
              const isOpen = openFaq === idx;
              return (
                <div
                  key={idx}
                  style={{
                    backgroundColor: 'var(--bg-surface)',
                    borderRadius: '16px',
                    border: '1px solid var(--border-color)',
                    overflow: 'hidden',
                    transition: 'all 0.2s',
                  }}
                >
                  <button
                    onClick={() => setOpenFaq(isOpen ? null : idx)}
                    style={{
                      width: '100%',
                      padding: '20px 24px',
                      display: 'flex',
                      alignItems: 'center',
                      justifyContent: 'space-between',
                      background: 'none',
                      border: 'none',
                      cursor: 'pointer',
                      textAlign: 'left',
                      fontWeight: 700,
                      fontSize: '1.02rem',
                      color: 'var(--text-main)',
                    }}
                  >
                    <span>{faq.q}</span>
                    <ChevronDown
                      size={20}
                      style={{
                        color: 'var(--text-muted)',
                        transform: isOpen ? 'rotate(180deg)' : 'rotate(0)',
                        transition: 'transform 0.2s',
                      }}
                    />
                  </button>
                  {isOpen && (
                    <div style={{ padding: '0 24px 20px', color: 'var(--text-muted)', fontSize: '0.92rem', lineHeight: 1.6 }}>
                      {faq.a}
                    </div>
                  )}
                </div>
              );
            })}
          </div>
        </section>

        {/* ═════════════════════════════════════════════════════════════════════
            CTA BANNER
        ═════════════════════════════════════════════ */}
        <section
          style={{
            background: 'linear-gradient(135deg, #0B1E17 0%, #0F392B 60%, #0d2e22 100%)',
            color: '#FFFFFF',
            padding: '80px 24px',
            textAlign: 'center',
            position: 'relative',
          }}
        >
          <div style={{ maxWidth: '720px', margin: '0 auto' }}>
            <h2 style={{ fontSize: '2.6rem', fontWeight: 800, marginBottom: '16px', letterSpacing: '-0.5px' }}>
              Stop Waiting. <br />
              <span style={{ color: 'var(--brand-lime)' }}>Start Owning.</span>
            </h2>
            <p style={{ color: 'rgba(255, 255, 255, 0.75)', fontSize: '1.05rem', marginBottom: '32px', lineHeight: 1.6 }}>
              It takes less than 5 minutes to begin. Secure your future with stable dividends, fast mobile credit, and real co-operative ownership.
            </p>
            <div style={{ display: 'flex', justifyContent: 'center', gap: '16px', flexWrap: 'wrap' }}>
              <Link href="/register" className="btn btn-lime btn-lg">
                Create Free Account <ArrowRight size={18} />
              </Link>
              <Link href="/login" className="btn btn-outline-lime btn-lg">
                Sign In to Portal
              </Link>
            </div>
          </div>
        </section>
      </main>

      <Footer />
    </div>
  );
}
