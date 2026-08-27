'use client';

import React, { useState, useEffect, useMemo, useRef } from 'react';
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
  CheckCircle2,
  Clock,
  Sparkles,
  Users,
  Search,
  Maximize2,
  X,
  PhoneCall,
  ExternalLink,
  Percent,
  Coins,
  Scale,
  Car,
  BadgeCheck,
  ChevronUp,
  Landmark,
  FileText,
  Play,
  Pause,
  Copy,
  Check,
  Layers,
  ArrowUp,
  Info,
} from 'lucide-react';
import { formatKES, formatNumber } from '@/lib/utils';

// Asset metadata for all 19 real images
interface SaccoAsset {
  id: number;
  image: string;
  title: string;
  category: 'fleet' | 'real-estate' | 'agri' | 'fuel' | 'welfare';
  categoryLabel: string;
  location: string;
  annualRoi: string;
  description: string;
  valuation: string;
}

const SACCO_ASSETS: SaccoAsset[] = [
  {
    id: 1,
    image: '/assets/images/sacco1.jpg',
    title: 'Naivasha Commercial Irrigation & Farm Transit Unit',
    category: 'agri',
    categoryLabel: 'Agribusiness & Transit',
    location: 'Rift Valley Agricultural Basin',
    annualRoi: '21.4% p.a.',
    description: 'Commercial farm utility fleet managing overhead sprinkler irrigation and produce transport across society farmlands.',
    valuation: 'KES 42M',
  },
  {
    id: 2,
    image: '/assets/images/sacco2.jpg',
    title: 'Umoja Energy Petroleum Station & Service Bay',
    category: 'fuel',
    categoryLabel: 'Fueling Station',
    location: 'Eastern Bypass Foothills',
    annualRoi: '22.8% p.a.',
    description: 'Sacco-owned retail fueling outlet with automated car wash, servicing bays, and convenience store generating daily cash profit.',
    valuation: 'KES 68M',
  },
  {
    id: 3,
    image: '/assets/images/sacco3.jpg',
    title: 'Rift Valley Commercial Cabbage Plantation',
    category: 'agri',
    categoryLabel: 'Commercial Horticulture',
    location: 'Kinangop Agricultural Zone',
    annualRoi: '19.5% p.a.',
    description: 'High-yield horticulture farming producing thousands of fresh cabbage heads weekly for major wholesale urban markets.',
    valuation: 'KES 35M',
  },
  {
    id: 4,
    image: '/assets/images/sacco4.jpg',
    title: 'Mount Longonot Grain & Maize Irrigation Scheme',
    category: 'agri',
    categoryLabel: 'Commercial Agribusiness',
    location: 'Naivasha South Corridor',
    annualRoi: '18.2% p.a.',
    description: 'Extensive cereal and commercial maize project fitted with modern center-pivot and high-pressure sprinkler irrigation.',
    valuation: 'KES 54M',
  },
  {
    id: 5,
    image: '/assets/images/sacco5.jpg',
    title: 'Umoja Agro-Logistics & Farm Haulage Vehicle',
    category: 'fleet',
    categoryLabel: 'Agro-Fleet Logistics',
    location: 'Central Agricultural Hub',
    annualRoi: '17.6% p.a.',
    description: 'Dedicated all-terrain commercial farm logistics truck transporting harvested crops from highland fields directly to distribution points.',
    valuation: 'KES 8.5M',
  },
  {
    id: 6,
    image: '/assets/images/sacco6.jpg',
    title: 'Highland Sprinkler Irrigation Project',
    category: 'agri',
    categoryLabel: 'Agribusiness',
    location: 'Nyandarua Foothills',
    annualRoi: '20.1% p.a.',
    description: 'Sustainable zero-drought irrigation system ensuring year-round crop production and consistent dividend generation.',
    valuation: 'KES 45M',
  },
  {
    id: 7,
    image: '/assets/images/sacco7.jpg',
    title: 'Kinangop Plateau Bulk Vegetable Field',
    category: 'agri',
    categoryLabel: 'Food Security Project',
    location: 'Aberdares Agricultural Belt',
    annualRoi: '18.9% p.a.',
    description: 'Prime highland fertile estate yielding premium horticultural produce supplying supermarkets and hospitals.',
    valuation: 'KES 48M',
  },
  {
    id: 8,
    image: '/assets/images/sacco8.jpg',
    title: 'Umoja Valley Fueling & Fleet Depot',
    category: 'fuel',
    categoryLabel: 'Petroleum Station',
    location: 'Kangundo Scenic Bypass',
    annualRoi: '23.0% p.a.',
    description: 'Strategic highway petroleum station offering member fuel discounts and high-margin retail lubricants.',
    valuation: 'KES 65M',
  },
  {
    id: 9,
    image: '/assets/images/sacco9.jpg',
    title: 'Uasin Gishu Commercial Corn Plantation',
    category: 'agri',
    categoryLabel: 'Agribusiness Hub',
    location: 'Eldoret Agricultural Zone',
    annualRoi: '19.8% p.a.',
    description: 'Large-scale commercial maize enterprise providing bulk grain storage and seasonal staple market supply.',
    valuation: 'KES 52M',
  },
  {
    id: 10,
    image: '/assets/images/sacco10.jpg',
    title: 'Valley Mist Cereal & Grain Basin',
    category: 'agri',
    categoryLabel: 'Commercial Agriculture',
    location: 'Nakuru Western Valley',
    annualRoi: '18.4% p.a.',
    description: 'Highland agricultural basin leveraging volcanic fertile soils for rich organic cereal production.',
    valuation: 'KES 39M',
  },
  {
    id: 11,
    image: '/assets/images/sacco11.jpg',
    title: 'Morning Sun High-Altitude Horticulture Farm',
    category: 'agri',
    categoryLabel: 'Horticulture Project',
    location: 'Mount Kenya Slopes',
    annualRoi: '21.0% p.a.',
    description: 'Commercial vegetable cultivation utilizing cool mountain climates and automated sprinkler systems.',
    valuation: 'KES 41M',
  },
  {
    id: 12,
    image: '/assets/images/sacco12.jpg',
    title: 'John Deere Heavy Agricultural Tractor Unit',
    category: 'fleet',
    categoryLabel: 'Mechanized Equipment',
    location: 'Central Machinery Hub',
    annualRoi: '24.5% p.a.',
    description: 'Heavy-duty high-horsepower tractor and mechanized tillage implement available for commercial leasing and farm operations.',
    valuation: 'KES 16.8M',
  },
  {
    id: 13,
    image: '/assets/images/sacco13.jpg',
    title: 'Member Women Agribusiness & Potato Empowerment Farm',
    category: 'welfare',
    categoryLabel: 'Welfare & Agribusiness',
    location: 'Meru Highland Corridor',
    annualRoi: 'Community Impact',
    description: 'Cooperative grassroots initiative supporting member families with land access, seed capital, and direct market linkage.',
    valuation: 'KES 28M',
  },
  {
    id: 14,
    image: '/assets/images/sacco14.jpg',
    title: 'Umoja Pure Honey & Apiculture Apiary Station',
    category: 'agri',
    categoryLabel: 'Apiculture & Honey',
    location: 'Kitui Agro-Forestry Reserve',
    annualRoi: '25.2% p.a.',
    description: 'Modern Langstroth beehive apiary producing premium organic raw honey and beeswax for export and retail.',
    valuation: 'KES 18M',
  },
  {
    id: 15,
    image: '/assets/images/sacco15.jpg',
    title: 'Red Soil Highland Horticulture & Legumes Farm',
    category: 'agri',
    categoryLabel: 'Commercial Agriculture',
    location: 'Embu Red Soil Basin',
    annualRoi: '20.6% p.a.',
    description: 'Certified legume and vegetable farming delivering high nutrition food crops and substantial recurring cash dividends.',
    valuation: 'KES 33M',
  },
  {
    id: 16,
    image: '/assets/images/sacco16.jpg',
    title: 'Modern Langstroth Hive Inspection & Honey Processing',
    category: 'agri',
    categoryLabel: 'Apiculture Center',
    location: 'Machakos Agro-Park',
    annualRoi: '24.0% p.a.',
    description: 'Specialized commercial beekeeping station with automated honey extractors and protective gear training programs.',
    valuation: 'KES 22M',
  },
  {
    id: 17,
    image: '/assets/images/sacco17.jpg',
    title: 'Umoja Greenview Residential Apartments & Courtyard',
    category: 'real-estate',
    categoryLabel: 'Commercial Real Estate',
    location: 'Kitengela Metro',
    annualRoi: '15.4% p.a.',
    description: 'Modern multi-family residential housing development featuring playgrounds, landscaping, and 100% tenant occupancy.',
    valuation: 'KES 115M',
  },
  {
    id: 18,
    image: '/assets/images/sacco18.jpg',
    title: 'Umoja Palms Luxury Gated Estate & Sports Complex',
    category: 'real-estate',
    categoryLabel: 'Prime Housing Development',
    location: 'Athi River Growth Corridor',
    annualRoi: '16.8% p.a.',
    description: 'Master-planned residential community featuring full swimming pools, tennis and basketball courts, and family apartments.',
    valuation: 'KES 195M',
  },
  {
    id: 19,
    image: '/assets/images/sacco19.jpg',
    title: 'Umoja Horizon Commercial High-Rise Towers',
    category: 'real-estate',
    categoryLabel: 'Commercial Real Estate',
    location: 'Nairobi Metro Fringe',
    annualRoi: '14.8% p.a.',
    description: 'Contemporary high-rise urban apartment tower generating steady long-term capital appreciation and monthly rental yields.',
    valuation: 'KES 160M',
  },
];

// Product Ecosystem Data
interface SaccoProduct {
  id: string;
  name: string;
  category: 'savings' | 'loans' | 'welfare' | 'investments';
  badge: string;
  interestRate: string;
  maxAmount: string;
  speed: string;
  description: string;
  features: string[];
  requirements: string[];
}

const SACCO_PRODUCTS: SaccoProduct[] = [
  {
    id: 'express-mobile',
    name: '15-Min Mobile Express Loan',
    category: 'loans',
    badge: 'Instant MPESA',
    interestRate: '8.0% p.a.',
    maxAmount: 'Up to KES 100,000',
    speed: 'Disbursed in 15 Mins',
    description: 'Instant mobile credit disbursed directly to your M-Pesa phone number 24/7 without paperwork or branch visits.',
    features: ['Zero paperwork', 'Repayable in 1 to 6 months', 'Guaranteed via your mobile deposits', 'No hidden appraisal fees'],
    requirements: ['Active member for at least 1 month', 'Minimum KES 3,000 savings balance', 'Clean repayment history'],
  },
  {
    id: 'asset-financing',
    name: 'Commercial Vehicle & PSV Financing',
    category: 'loans',
    badge: 'Asset Ownership',
    interestRate: '10.5% p.a.',
    maxAmount: 'Up to KES 4,000,000',
    speed: 'Approved in 48 Hours',
    description: 'Acquire your own 14-seater matatu, 33-seater bus, prime mover, or delivery van with flexible commercial daily payments.',
    features: ['Up to 80% vehicle financing', 'Up to 48 months repayment', 'Includes GPS tracker & insurance discounts', 'Sacco route assignment support'],
    requirements: ['6 months member standing', '20% deposit/equity contribution', 'Logbook deposited with Sacco'],
  },
  {
    id: 'super-dev',
    name: 'Super Development Loan',
    category: 'loans',
    badge: 'Wealth Builder',
    interestRate: '10.0% p.a. Reducing',
    maxAmount: 'Up to 3x Savings (KES 2.5M)',
    speed: 'Processed in 24 Hours',
    description: 'Low-interest development capital for buying land, constructing rental property, expanding businesses, or farming.',
    features: ['3x borrowing power on deposits', 'Up to 36 months duration', 'Reducing balance interest formula', 'Free financial advisory session'],
    requirements: ['Minimum KES 30,000 core deposits', '2 member guarantors or collateral', 'Clean credit record'],
  },
  {
    id: 'compounding-deposits',
    name: 'High-Yield Compounding Shares & Deposits',
    category: 'savings',
    badge: '14.5% Annual Dividends',
    interestRate: '14.5% Historical Yield',
    maxAmount: 'Unlimited',
    speed: 'Daily Deposits Accepted',
    description: 'Put your money to work in SASRA-regulated pooled funds earning market-beating annual dividend payouts.',
    features: ['Compound annual dividend growth', 'Qualifies for 3x loan credit limit', 'Safe & SASRA protected', 'Daily or monthly MPESA deposits'],
    requirements: ['Valid National ID / Passport', 'KES 1,000 initial share capital', 'KES 1,000 min monthly contribution'],
  },
  {
    id: 'daily-float',
    name: 'Driver Daily Float & Target Savings',
    category: 'savings',
    badge: 'Daily Discipline',
    interestRate: '7.5% p.a. Bonus',
    maxAmount: 'Custom Target',
    speed: 'Instant MPESA STK Push',
    description: 'Automated daily savings of KES 50, 100, or 200 deducted from your daily matatu/boda earnings towards a designated goal.',
    features: ['Automated daily USSD / MPESA prompt', 'Earn bonus annual interest', 'Lock funds until target date', 'Emergency withdrawal allowed'],
    requirements: ['Any active registered member', 'Set your daily target amount'],
  },
  {
    id: 'welfare-shield',
    name: 'Solidarity & Emergency Relief Shield',
    category: 'welfare',
    badge: 'Complete Family Cover',
    interestRate: 'Zero Cost Benefit',
    maxAmount: 'KES 250,000 Grant Limit',
    speed: 'Immediate Relief Fund',
    description: 'A compassionate safety net protecting you, your spouse, children, and parents in times of medical or road emergencies.',
    features: ['Hospital admission cash grant', 'Road accident medical coverage', 'Bereavement compassionate payout', 'Legal defense assistance'],
    requirements: ['Monthly welfare dues of KES 300', 'Registered direct beneficiaries'],
  },
];

export default function LandingPage() {
  // Slideshow State (19 Sacco Asset Images)
  const totalSlides = SACCO_ASSETS.length;
  const [currentSlide, setCurrentSlide] = useState(0);
  const [isAutoPlaying, setIsAutoPlaying] = useState(true);
  const [isHoveringSlideshow, setIsHoveringSlideshow] = useState(false);
  const [slideProgress, setSlideProgress] = useState(0);

  // Selected Asset for Lightbox Modal
  const [selectedAssetIndex, setSelectedAssetIndex] = useState<number | null>(null);

  // Selected Product for Details Modal
  const [selectedProduct, setSelectedProduct] = useState<SaccoProduct | null>(null);

  // Active Category Filter for Portfolio Gallery
  const [portfolioCategory, setPortfolioCategory] = useState<string>('all');
  const [portfolioSearch, setPortfolioSearch] = useState<string>('');

  // Calculator Tab: 'loan' or 'savings'
  const [calcTab, setCalcTab] = useState<'loan' | 'savings'>('loan');

  // Loan Calculator State
  const [loanType, setLoanType] = useState<'express' | 'dev' | 'asset' | 'emergency'>('dev');
  const [loanAmount, setLoanAmount] = useState<number>(150000);
  const [loanMonths, setLoanMonths] = useState<number>(12);
  const [showAmortization, setShowAmortization] = useState(false);
  const [copiedQuote, setCopiedQuote] = useState(false);

  // Rates based on type
  const loanRates = {
    express: { rate: 0.08, label: 'Express Mobile (8% p.a.)', minAmt: 5000, maxAmt: 100000, minMos: 1, maxMos: 6 },
    dev: { rate: 0.10, label: 'Development Loan (10% p.a.)', minAmt: 20000, maxAmt: 2000000, minMos: 3, maxMos: 36 },
    asset: { rate: 0.12, label: 'Asset & Vehicle Financing (12% p.a.)', minAmt: 100000, maxAmt: 4000000, minMos: 6, maxMos: 48 },
    emergency: { rate: 0.06, label: 'Emergency / School Fees (6% p.a.)', minAmt: 5000, maxAmt: 80000, minMos: 1, maxMos: 4 },
  };

  const currentRateObj = loanRates[loanType];
  const totalInterest = loanAmount * (currentRateObj.rate * (loanMonths / 12));
  const totalRepayable = loanAmount + totalInterest;
  const monthlyInstallment = totalRepayable / loanMonths;

  // Generate interactive Amortization Schedule
  const amortizationSchedule = useMemo(() => {
    const rows = [];
    const monthlyRate = currentRateObj.rate / 12;
    const monthlyPayment = totalRepayable / loanMonths;
    let balance = totalRepayable;

    for (let i = 1; i <= loanMonths; i++) {
      const interestPortion = (loanAmount * currentRateObj.rate) / 12;
      const principalPortion = monthlyPayment - interestPortion;
      balance = Math.max(0, balance - monthlyPayment);

      rows.push({
        month: i,
        payment: monthlyPayment,
        principal: principalPortion,
        interest: interestPortion,
        remaining: balance,
      });
    }
    return rows;
  }, [loanAmount, loanMonths, currentRateObj, totalRepayable]);

  // Savings / Compound Wealth Projector State
  const [monthlySavings, setMonthlySavings] = useState<number>(5000);
  const [savingsYears, setSavingsYears] = useState<number>(5);
  const [annualDividendRate, setAnnualDividendRate] = useState<number>(14.5); // 14.5%

  // Compound savings calculation
  const savingsProjection = useMemo(() => {
    let balance = 0;
    let totalDeposited = 0;
    const monthlyRate = annualDividendRate / 100 / 12;
    const totalMonths = savingsYears * 12;

    for (let m = 1; m <= totalMonths; m++) {
      balance = (balance + monthlySavings) * (1 + monthlyRate);
      totalDeposited += monthlySavings;
    }
    const totalDividendsEarned = Math.max(0, balance - totalDeposited);
    const loanBorrowingCapacity = balance * 3;

    // Milestone calculations
    const calcAtYear = (yr: number) => {
      let b = 0;
      for (let m = 1; m <= yr * 12; m++) {
        b = (b + monthlySavings) * (1 + monthlyRate);
      }
      return b;
    };

    return {
      finalBalance: balance,
      totalDeposited,
      totalDividendsEarned,
      loanBorrowingCapacity,
      y1: calcAtYear(1),
      y3: calcAtYear(3),
      y5: calcAtYear(5),
      y10: calcAtYear(10),
    };
  }, [monthlySavings, savingsYears, annualDividendRate]);

  // Product Ecosystem Category Filter
  const [productCategory, setProductCategory] = useState<string>('all');

  // FAQ Accordion State & Search Filter
  const [openFaq, setOpenFaq] = useState<number | null>(0);
  const [faqSearch, setFaqSearch] = useState<string>('');
  const [faqCategory, setFaqCategory] = useState<string>('all');

  // Floating Calculator Drawer State
  const [floatingDrawerOpen, setFloatingDrawerOpen] = useState(false);
  const [showScrollTop, setShowScrollTop] = useState(false);

  // Social Proof Toast State
  const [socialToast, setSocialToast] = useState<{ name: string; action: string; time: string } | null>({
    name: 'Josephat K. from Nairobi',
    action: 'received KES 120,000 Asset Loan',
    time: '2 mins ago',
  });

  const faqs = [
    {
      category: 'membership',
      q: 'Who is eligible to join Umoja Sacco?',
      a: 'Membership is open to commercial drivers, public transport operators, courier and delivery personnel, fleet owners, bodaboda operators, mechanics, and allied transport professionals across Kenya.',
    },
    {
      category: 'loans',
      q: 'How fast can I get a loan approved and disbursed?',
      a: 'Emergency and 15-Minute Mobile Express loans are approved and disbursed via M-Pesa within 15 minutes. Normal development and asset financing loans are processed within 24 to 48 hours with transparent appraisal.',
    },
    {
      category: 'savings',
      q: 'What are the minimum monthly savings required?',
      a: 'The minimum monthly savings contribution is KES 1,000. These savings earn compounding annual dividend interest (14.5% historical avg.) and qualify you for up to 3x your savings in loan borrowing power.',
    },
    {
      category: 'welfare',
      q: 'How does the Welfare & Solidarity Fund protect members?',
      a: 'The Welfare Fund provides immediate cash relief for hospitalization, bereavement, road accident emergency assistance, and legal defense without touching your core savings.',
    },
    {
      category: 'loans',
      q: 'Do I need physical logbooks or land title deeds to get a loan?',
      a: 'For Mobile and Development loans up to 3x your savings, you only need your accumulated savings and member guarantors. For vehicle financing above your savings limit, the vehicle logbook serves as the collateral.',
    },
    {
      category: 'savings',
      q: 'How are annual dividends calculated and paid out?',
      a: 'Dividends are approved at the Annual General Meeting (AGM) based on Sacco asset profits (fleets, commercial rent, agribusiness, loan interest). Dividends are paid directly to your member wallet or M-Pesa account.',
    },
    {
      category: 'security',
      q: 'Is Umoja Sacco officially regulated and safe?',
      a: 'Yes, Umoja Sacco is fully regulated by SASRA (Sacco Societies Regulatory Authority) and complies with all Kenyan co-operative laws, audited annually by certified statutory auditors.',
    },
  ];

  const filteredFaqs = useMemo(() => {
    return faqs.filter((faq) => {
      const matchCat = faqCategory === 'all' || faq.category === faqCategory;
      const matchSearch =
        faq.q.toLowerCase().includes(faqSearch.toLowerCase()) ||
        faq.a.toLowerCase().includes(faqSearch.toLowerCase());
      return matchCat && matchSearch;
    });
  }, [faqs, faqCategory, faqSearch]);

  // Filtered Assets for Gallery
  const filteredAssets = useMemo(() => {
    return SACCO_ASSETS.filter((a) => {
      const matchCat = portfolioCategory === 'all' || a.category === portfolioCategory;
      const matchSearch =
        a.title.toLowerCase().includes(portfolioSearch.toLowerCase()) ||
        a.location.toLowerCase().includes(portfolioSearch.toLowerCase()) ||
        a.description.toLowerCase().includes(portfolioSearch.toLowerCase());
      return matchCat && matchSearch;
    });
  }, [portfolioCategory, portfolioSearch]);

  // Filtered Products
  const filteredProducts = useMemo(() => {
    if (productCategory === 'all') return SACCO_PRODUCTS;
    return SACCO_PRODUCTS.filter((p) => p.category === productCategory);
  }, [productCategory]);

  // Auto slide timer with progress bar
  useEffect(() => {
    if (!isAutoPlaying || isHoveringSlideshow || selectedAssetIndex !== null) return;
    const intervalMs = 4500;
    const stepMs = 50;
    let elapsed = 0;

    const timer = setInterval(() => {
      elapsed += stepMs;
      setSlideProgress((elapsed / intervalMs) * 100);

      if (elapsed >= intervalMs) {
        elapsed = 0;
        setCurrentSlide((prev) => (prev + 1) % totalSlides);
      }
    }, stepMs);

    return () => clearInterval(timer);
  }, [totalSlides, isAutoPlaying, isHoveringSlideshow, selectedAssetIndex]);

  // Scroll listener for back-to-top button
  useEffect(() => {
    const handleScroll = () => {
      setShowScrollTop(window.scrollY > 450);
    };
    window.addEventListener('scroll', handleScroll);
    return () => window.removeEventListener('scroll', handleScroll);
  }, []);

  // Keyboard navigation for Lightbox & Slideshow
  useEffect(() => {
    const handleKeyDown = (e: KeyboardEvent) => {
      if (selectedAssetIndex !== null) {
        if (e.key === 'ArrowRight') {
          setSelectedAssetIndex((prev) => (prev !== null ? (prev + 1) % totalSlides : 0));
        } else if (e.key === 'ArrowLeft') {
          setSelectedAssetIndex((prev) => (prev !== null ? (prev - 1 + totalSlides) % totalSlides : 0));
        } else if (e.key === 'Escape') {
          setSelectedAssetIndex(null);
        }
      }
    };
    window.addEventListener('keydown', handleKeyDown);
    return () => window.removeEventListener('keydown', handleKeyDown);
  }, [selectedAssetIndex, totalSlides]);

  // Social proof rotating notifications
  useEffect(() => {
    const notifications = [
      { name: 'Josephat K. from Nairobi', action: 'received KES 150,000 PSV Financing', time: '2m ago' },
      { name: 'Mary W. from Nakuru', action: 'deposited KES 10,000 monthly shares', time: '5m ago' },
      { name: 'Peter O. from Eldoret', action: 'approved for KES 45,000 Mobile Express', time: '8m ago' },
      { name: 'Brian M. from Kiambu', action: 'joined Umoja Sacco today', time: '12m ago' },
    ];
    let idx = 0;
    const interval = setInterval(() => {
      idx = (idx + 1) % notifications.length;
      setSocialToast(notifications[idx]);
    }, 9000);
    return () => clearInterval(interval);
  }, []);

  const prevSlide = () => {
    setSlideProgress(0);
    setCurrentSlide((prev) => (prev - 1 + totalSlides) % totalSlides);
  };

  const nextSlide = () => {
    setSlideProgress(0);
    setCurrentSlide((prev) => (prev + 1) % totalSlides);
  };

  const copyQuote = () => {
    const text = `Umoja Sacco Loan Quote:\nLoan Type: ${currentRateObj.label}\nPrincipal: ${formatKES(loanAmount)}\nDuration: ${loanMonths} Months\nMonthly EMI: ${formatKES(monthlyInstallment)}\nTotal Interest: ${formatKES(totalInterest)}\nTotal Repayable: ${formatKES(totalRepayable)}`;
    navigator.clipboard.writeText(text);
    setCopiedQuote(true);
    setTimeout(() => setCopiedQuote(false), 2500);
  };

  return (
    <div style={{ minHeight: '100vh', display: 'flex', flexDirection: 'column', backgroundColor: 'var(--bg-primary)' }}>
      {/* ═════════════════════════════════════════════════════════════════════
          TOP ANNOUNCEMENT & LIVE TICKER BAR
      ═════════════════════════════════════════════ */}
      <div
        style={{
          backgroundColor: '#07140F',
          color: '#FFFFFF',
          padding: '8px 16px',
          fontSize: '0.76rem',
          borderBottom: '1px solid rgba(208, 247, 100, 0.15)',
          display: 'flex',
          alignItems: 'center',
          justifyContent: 'space-between',
          flexWrap: 'wrap',
          gap: '8px',
        }}
      >
        <div style={{ display: 'flex', alignItems: 'center', gap: '8px', flexWrap: 'wrap' }}>
          <span className="live-indicator" />
          <span style={{ fontWeight: 700, color: 'var(--brand-lime)' }}>LIVE:</span>
          <span>Annual Dividend declared at <b>14.5% p.a.</b> • M-Pesa Engine: <b style={{ color: '#22c55e' }}>ONLINE</b></span>
        </div>
        <div className="d-none d-md-flex" style={{ alignItems: 'center', gap: '16px', color: 'rgba(255,255,255,0.75)' }}>
          <span style={{ display: 'flex', alignItems: 'center', gap: '5px' }}>
            <ShieldCheck size={13} color="var(--brand-lime)" /> SASRA Registered
          </span>
          <span style={{ display: 'flex', alignItems: 'center', gap: '5px' }}>
            <PhoneCall size={13} color="var(--brand-lime)" /> 0800 000 786
          </span>
        </div>
      </div>

      <Navbar />

      <main style={{ flex: 1 }}>
        {/* ═════════════════════════════════════════════════════════════════════
            HERO SECTION WITH 3D POKER CARD SLIDESHOW
        ═════════════════════════════════════════════ */}
        <section
          style={{
            position: 'relative',
            background: `linear-gradient(155deg, rgba(7, 20, 15, 0.96) 0%, rgba(11, 36, 25, 0.93) 50%, rgba(5, 15, 11, 0.98) 100%), url('/assets/images/sacco3.jpg') center/cover no-repeat`,
            color: '#FFFFFF',
            padding: '70px 20px 90px',
            overflow: 'hidden',
          }}
        >
          {/* Ambient Lighting Orbs */}
          <div
            style={{
              position: 'absolute',
              top: '-150px',
              right: '-100px',
              width: '600px',
              height: '600px',
              background: 'radial-gradient(circle, rgba(163, 230, 53, 0.18) 0%, transparent 70%)',
              borderRadius: '50%',
              pointerEvents: 'none',
            }}
          />
          <div
            style={{
              position: 'absolute',
              bottom: '-120px',
              left: '-80px',
              width: '450px',
              height: '450px',
              background: 'radial-gradient(circle, rgba(226, 179, 74, 0.12) 0%, transparent 65%)',
              borderRadius: '50%',
              pointerEvents: 'none',
            }}
          />

          <div
            style={{
              maxWidth: '1240px',
              margin: '0 auto',
              display: 'grid',
              gridTemplateColumns: 'repeat(auto-fit, minmax(290px, 1fr))',
              gap: '40px',
              alignItems: 'center',
              position: 'relative',
              zIndex: 3,
            }}
          >
            {/* Left Column: Value Proposition */}
            <div>
              <div
                className="eyebrow-pill"
                style={{
                  marginBottom: '18px',
                  boxShadow: '0 4px 14px rgba(163, 230, 53, 0.2)',
                  fontSize: 'clamp(0.65rem, 2.5vw, 0.72rem)',
                }}
              >
                <span className="eyebrow-dot" /> Transport Sector Co-Operative • SASRA Regulated
              </div>

              <h1
                className="hero-heading"
                style={{
                  fontSize: 'clamp(2.1rem, 5.5vw, 3.8rem)',
                  fontWeight: 800,
                  lineHeight: 1.1,
                  letterSpacing: '-1.2px',
                  marginBottom: '18px',
                }}
              >
                Own The Fleets. <br />
                Earn The Dividends. <br />
                <span className="gradient-text-lime">Master Your Wealth.</span>
              </h1>

              <p
                style={{
                  fontSize: 'clamp(0.92rem, 2.8vw, 1.05rem)',
                  lineHeight: 1.65,
                  color: 'rgba(255, 255, 255, 0.85)',
                  marginBottom: '32px',
                  maxWidth: '540px',
                }}
              >
                Umoja Sacco pools daily transport contributions into revenue-generating{' '}
                <b style={{ color: 'var(--brand-lime)' }}>commercial matatu fleets, commercial real estate, agribusiness, and fueling stations</b>{' '}
                — guaranteeing <b>14.5% annual dividends</b> and <b>15-minute MPESA loans</b>.
              </p>

              {/* Action Buttons */}
              <div style={{ display: 'flex', flexWrap: 'wrap', gap: '12px', marginBottom: '36px' }}>
                <Link href="/register" className="btn btn-lime btn-lg btn-mobile-block" style={{ boxShadow: '0 8px 24px rgba(163, 230, 53, 0.4)' }}>
                  Join Sacco Today <ArrowRight size={18} />
                </Link>
                <a href="#calculator" className="btn btn-outline-lime btn-lg btn-mobile-block">
                  <Calculator size={18} /> Calculate Loan & Returns
                </a>
              </div>

              {/* Trust Indicators */}
              <div
                style={{
                  display: 'grid',
                  gridTemplateColumns: 'repeat(auto-fit, minmax(85px, 1fr))',
                  gap: '12px',
                  borderTop: '1px solid rgba(255, 255, 255, 0.15)',
                  paddingTop: '20px',
                }}
              >
                <div>
                  <div style={{ fontSize: 'clamp(1.15rem, 4vw, 1.45rem)', fontWeight: 800, color: 'var(--brand-lime)', lineHeight: 1 }}>
                    14.5%
                  </div>
                  <div style={{ fontSize: '0.68rem', fontWeight: 700, color: 'rgba(255,255,255,0.7)', textTransform: 'uppercase', marginTop: '6px' }}>
                    Avg. Dividend
                  </div>
                </div>
                <div style={{ borderLeft: '1px solid rgba(255,255,255,0.15)', paddingLeft: '12px' }}>
                  <div style={{ fontSize: 'clamp(1.15rem, 4vw, 1.45rem)', fontWeight: 800, color: 'var(--brand-lime)', lineHeight: 1 }}>
                    15 Mins
                  </div>
                  <div style={{ fontSize: '0.68rem', fontWeight: 700, color: 'rgba(255,255,255,0.7)', textTransform: 'uppercase', marginTop: '6px' }}>
                    MPESA Payout
                  </div>
                </div>
                <div style={{ borderLeft: '1px solid rgba(255,255,255,0.15)', paddingLeft: '12px' }}>
                  <div style={{ fontSize: 'clamp(1.15rem, 4vw, 1.45rem)', fontWeight: 800, color: 'var(--brand-lime)', lineHeight: 1 }}>
                    KES 650M+
                  </div>
                  <div style={{ fontSize: '0.68rem', fontWeight: 700, color: 'rgba(255,255,255,0.7)', textTransform: 'uppercase', marginTop: '6px' }}>
                    Asset Base
                  </div>
                </div>
              </div>
            </div>

            {/* Right Column: 3D Poker Card Interactive Slideshow */}
            <div
              style={{ display: 'flex', flexDirection: 'column', alignItems: 'center', justifyContent: 'center', width: '100%' }}
              onMouseEnter={() => setIsHoveringSlideshow(true)}
              onMouseLeave={() => setIsHoveringSlideshow(false)}
            >
              {/* Top Controls: Counter & Play/Pause */}
              <div
                style={{
                  display: 'flex',
                  alignItems: 'center',
                  justifyContent: 'space-between',
                  width: '100%',
                  maxWidth: '380px',
                  marginBottom: '10px',
                  padding: '0 8px',
                }}
              >
                <div style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
                  <button
                    onClick={() => setIsAutoPlaying(!isAutoPlaying)}
                    title={isAutoPlaying ? 'Pause Slideshow' : 'Resume Slideshow'}
                    style={{
                      background: 'rgba(255,255,255,0.1)',
                      border: 'none',
                      color: 'var(--brand-lime)',
                      borderRadius: '50%',
                      width: '28px',
                      height: '28px',
                      display: 'flex',
                      alignItems: 'center',
                      justifyContent: 'center',
                      cursor: 'pointer',
                    }}
                  >
                    {isAutoPlaying ? <Pause size={13} /> : <Play size={13} />}
                  </button>
                  <span style={{ fontSize: '0.76rem', fontWeight: 700, color: 'var(--brand-lime)', textTransform: 'uppercase' }}>
                    Asset #{currentSlide + 1} of {totalSlides}
                  </span>
                </div>
                <span
                  style={{
                    fontSize: '0.75rem',
                    fontWeight: 800,
                    background: 'rgba(255,255,255,0.1)',
                    padding: '2px 10px',
                    borderRadius: '50px',
                    color: '#FFFFFF',
                  }}
                >
                  {String(currentSlide + 1).padStart(2, '0')} / {totalSlides}
                </span>
              </div>

              {/* Countdown Progress Bar */}
              <div style={{ width: '100%', maxWidth: '380px', marginBottom: '12px', padding: '0 8px' }}>
                <div className="autoplay-progress-bar">
                  <div className="autoplay-progress-fill" style={{ width: isAutoPlaying ? `${slideProgress}%` : '0%' }} />
                </div>
              </div>

              {/* 3D Stack Container */}
              <div
                className="poker-slideshow-container"
                style={{
                  perspective: '1200px',
                  width: '100%',
                  maxWidth: '380px',
                  height: '420px',
                  position: 'relative',
                  display: 'flex',
                  alignItems: 'center',
                  justifyContent: 'center',
                }}
              >
                {SACCO_ASSETS.map((asset, idx) => {
                  const isActive = idx === currentSlide;
                  const isLeft = idx === (currentSlide - 1 + totalSlides) % totalSlides;
                  const isRight = idx === (currentSlide + 1) % totalSlides;

                  let transform = 'translateZ(-220px) scale(0.75)';
                  let opacity = 0;
                  let zIndex = 1;
                  let pointerEvents: 'none' | 'auto' = 'none';

                  if (isActive) {
                    transform = 'rotateY(0deg) translateZ(0) scale(1.05)';
                    opacity = 1;
                    zIndex = 10;
                    pointerEvents = 'auto';
                  } else if (isLeft) {
                    transform = 'rotateY(22deg) translateX(-120px) translateZ(-90px) scale(0.85)';
                    opacity = 0.55;
                    zIndex = 5;
                  } else if (isRight) {
                    transform = 'rotateY(-22deg) translateX(120px) translateZ(-90px) scale(0.85)';
                    opacity = 0.55;
                    zIndex = 5;
                  }

                  return (
                    <div
                      key={asset.id}
                      className="poker-card-hero"
                      onClick={() => (isActive ? setSelectedAssetIndex(idx) : setCurrentSlide(idx))}
                      title={isActive ? 'Click to inspect high-resolution details' : `View Asset #${asset.id}`}
                      style={{
                        position: 'absolute',
                        width: '280px',
                        height: '380px',
                        borderRadius: '24px',
                        backgroundColor: '#FFFFFF',
                        border: isActive ? '4px solid var(--brand-lime)' : '4px solid rgba(255, 255, 255, 0.4)',
                        boxShadow: isActive ? '0 24px 60px rgba(0,0,0,0.6), 0 0 25px rgba(163, 230, 53, 0.3)' : '0 12px 30px rgba(0,0,0,0.3)',
                        transition: 'all 0.55s cubic-bezier(0.23, 1, 0.32, 1)',
                        transform,
                        opacity,
                        zIndex,
                        cursor: 'pointer',
                        overflow: 'hidden',
                        pointerEvents,
                      }}
                    >
                      <img
                        src={asset.image}
                        alt={asset.title}
                        style={{
                          width: '100%',
                          height: '100%',
                          objectFit: 'cover',
                          borderRadius: '19px',
                          display: 'block',
                        }}
                      />

                      {/* Top Overlay Badge */}
                      <div
                        style={{
                          position: 'absolute',
                          top: '14px',
                          left: '14px',
                          right: '14px',
                          display: 'flex',
                          justifyContent: 'space-between',
                          alignItems: 'center',
                        }}
                      >
                        <span
                          style={{
                            background: 'rgba(7, 20, 15, 0.85)',
                            backdropFilter: 'blur(6px)',
                            color: 'var(--brand-lime)',
                            fontSize: '0.68rem',
                            fontWeight: 800,
                            padding: '4px 10px',
                            borderRadius: '50px',
                            border: '1px solid rgba(163, 230, 53, 0.3)',
                            textTransform: 'uppercase',
                          }}
                        >
                          {asset.categoryLabel}
                        </span>
                        {isActive && (
                          <span
                            style={{
                              background: 'rgba(7, 20, 15, 0.85)',
                              backdropFilter: 'blur(6px)',
                              color: '#FFFFFF',
                              fontSize: '0.65rem',
                              fontWeight: 700,
                              padding: '4px 8px',
                              borderRadius: '50px',
                              display: 'flex',
                              alignItems: 'center',
                              gap: '4px',
                            }}
                          >
                            <Maximize2 size={11} color="var(--brand-lime)" /> Zoom
                          </span>
                        )}
                      </div>

                      {/* Bottom Info Overlay */}
                      <div
                        style={{
                          position: 'absolute',
                          bottom: 0,
                          left: 0,
                          right: 0,
                          padding: '18px 16px 14px',
                          background: 'linear-gradient(to top, rgba(7, 20, 15, 0.95) 0%, rgba(7, 20, 15, 0.7) 65%, transparent 100%)',
                          color: '#FFFFFF',
                        }}
                      >
                        <div style={{ fontSize: '0.88rem', fontWeight: 800, color: '#FFFFFF', lineHeight: 1.25, marginBottom: '4px' }}>
                          {asset.title}
                        </div>
                        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', fontSize: '0.74rem' }}>
                          <span style={{ color: 'rgba(255,255,255,0.7)' }}>{asset.location}</span>
                          <span style={{ color: 'var(--brand-lime)', fontWeight: 800 }}>{asset.annualRoi}</span>
                        </div>
                      </div>
                    </div>
                  );
                })}
              </div>

              {/* Interactive Thumbnail Selector Bar */}
              <div
                style={{
                  display: 'flex',
                  gap: '6px',
                  maxWidth: '380px',
                  overflowX: 'auto',
                  padding: '8px 4px',
                  marginTop: '12px',
                  zIndex: 20,
                  WebkitOverflowScrolling: 'touch',
                }}
              >
                {SACCO_ASSETS.map((asset, idx) => (
                  <button
                    key={asset.id}
                    onClick={() => {
                      setSlideProgress(0);
                      setCurrentSlide(idx);
                    }}
                    title={`Jump to ${asset.title}`}
                    style={{
                      width: idx === currentSlide ? '32px' : '22px',
                      height: '24px',
                      borderRadius: '6px',
                      border: idx === currentSlide ? '2px solid var(--brand-lime)' : '1px solid rgba(255,255,255,0.2)',
                      padding: 0,
                      cursor: 'pointer',
                      overflow: 'hidden',
                      flexShrink: 0,
                      transition: 'all 0.2s',
                    }}
                  >
                    <img src={asset.image} alt={asset.title} style={{ width: '100%', height: '100%', objectFit: 'cover' }} />
                  </button>
                ))}
              </div>

              {/* Slideshow Controls */}
              <div style={{ display: 'flex', alignItems: 'center', gap: '16px', marginTop: '8px', zIndex: 20 }}>
                <button
                  onClick={prevSlide}
                  title="Previous Asset"
                  style={{
                    width: '42px',
                    height: '42px',
                    borderRadius: '12px',
                    backgroundColor: 'rgba(163, 230, 53, 0.12)',
                    border: '1px solid rgba(163, 230, 53, 0.35)',
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
                  onClick={() => setSelectedAssetIndex(currentSlide)}
                  className="btn btn-outline-lime"
                  style={{ padding: '6px 16px', fontSize: '0.82rem' }}
                >
                  <Maximize2 size={13} /> View Full Specs
                </button>

                <button
                  onClick={nextSlide}
                  title="Next Asset"
                  style={{
                    width: '42px',
                    height: '42px',
                    borderRadius: '12px',
                    backgroundColor: 'rgba(163, 230, 53, 0.12)',
                    border: '1px solid rgba(163, 230, 53, 0.35)',
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
            LIVE METRICS & REGULATORY TRUST RIBBON
        ═════════════════════════════════════════════ */}
        <section
          style={{
            background: 'linear-gradient(135deg, #0B2419 0%, #103425 100%)',
            padding: '36px 24px',
            color: '#FFFFFF',
            borderBottom: '1px solid rgba(163, 230, 53, 0.2)',
          }}
        >
          <div
            style={{
              maxWidth: '1240px',
              margin: '0 auto',
              display: 'grid',
              gridTemplateColumns: 'repeat(auto-fit, minmax(220px, 1fr))',
              gap: '28px',
              textAlign: 'center',
            }}
          >
            <div className="card-hover" style={{ padding: '16px', borderRadius: '16px', background: 'rgba(255,255,255,0.03)' }}>
              <div style={{ fontSize: '2.2rem', fontWeight: 800, color: 'var(--brand-lime)', lineHeight: 1 }}>14.5%</div>
              <div style={{ fontSize: '0.75rem', fontWeight: 700, color: 'rgba(255,255,255,0.7)', textTransform: 'uppercase', marginTop: '8px' }}>
                Annual Dividend Yield
              </div>
              <div style={{ fontSize: '0.7rem', color: 'rgba(255,255,255,0.5)', marginTop: '2px' }}>Audited historical returns</div>
            </div>

            <div className="card-hover" style={{ padding: '16px', borderRadius: '16px', background: 'rgba(255,255,255,0.03)' }}>
              <div style={{ fontSize: '2.2rem', fontWeight: 800, color: 'var(--brand-lime)', lineHeight: 1 }}>KES 650M+</div>
              <div style={{ fontSize: '0.75rem', fontWeight: 700, color: 'rgba(255,255,255,0.7)', textTransform: 'uppercase', marginTop: '8px' }}>
                Asset & Capital Base
              </div>
              <div style={{ fontSize: '0.7rem', color: 'rgba(255,255,255,0.5)', marginTop: '2px' }}>Real tangible investments</div>
            </div>

            <div className="card-hover" style={{ padding: '16px', borderRadius: '16px', background: 'rgba(255,255,255,0.03)' }}>
              <div style={{ fontSize: '2.2rem', fontWeight: 800, color: 'var(--brand-lime)', lineHeight: 1 }}>15 Mins</div>
              <div style={{ fontSize: '0.75rem', fontWeight: 700, color: 'rgba(255,255,255,0.7)', textTransform: 'uppercase', marginTop: '8px' }}>
                Mobile Loan Payout
              </div>
              <div style={{ fontSize: '0.7rem', color: 'rgba(255,255,255,0.5)', marginTop: '2px' }}>Direct to M-Pesa 24/7</div>
            </div>

            <div className="card-hover" style={{ padding: '16px', borderRadius: '16px', background: 'rgba(255,255,255,0.03)' }}>
              <div style={{ fontSize: '2.2rem', fontWeight: 800, color: 'var(--brand-lime)', lineHeight: 1 }}>12,400+</div>
              <div style={{ fontSize: '0.75rem', fontWeight: 700, color: 'rgba(255,255,255,0.7)', textTransform: 'uppercase', marginTop: '8px' }}>
                Transport Members
              </div>
              <div style={{ fontSize: '0.7rem', color: 'rgba(255,255,255,0.5)', marginTop: '2px' }}>Countrywide network</div>
            </div>
          </div>
        </section>

        {/* ═════════════════════════════════════════════════════════════════════
            THE UMOJA WEALTH BLUEPRINT (4-STEP JOURNEY)
        ═════════════════════════════════════════════ */}
        <section id="wealth-model" style={{ padding: '96px 24px', maxWidth: '1240px', margin: '0 auto' }}>
          <div style={{ textAlign: 'center', marginBottom: '60px' }}>
            <span className="eyebrow-pill" style={{ marginBottom: '14px' }}>
              <Sparkles size={13} /> The Wealth Blueprint
            </span>
            <h2 style={{ fontSize: '2.5rem', fontWeight: 800, color: 'var(--text-main)', letterSpacing: '-0.8px' }}>
              How Collective Ownership Multiplies Your Wealth
            </h2>
            <p style={{ color: 'var(--text-muted)', fontSize: '1.05rem', maxWidth: '640px', margin: '14px auto 0' }}>
              A proven co-operative engine transforming small daily driver contributions into high-yielding commercial assets.
            </p>
          </div>

          <div
            style={{
              display: 'grid',
              gridTemplateColumns: 'repeat(auto-fit, minmax(260px, 1fr))',
              gap: '24px',
              position: 'relative',
            }}
          >
            {/* Step 1 */}
            <div className="benefit-grid-card">
              <div
                style={{
                  width: '48px',
                  height: '48px',
                  borderRadius: '14px',
                  backgroundColor: 'var(--brand-forest)',
                  color: 'var(--brand-lime)',
                  fontSize: '1rem',
                  fontWeight: 800,
                  display: 'flex',
                  alignItems: 'center',
                  justifyContent: 'center',
                  marginBottom: '20px',
                  boxShadow: '0 4px 12px rgba(11, 36, 25, 0.2)',
                }}
              >
                1
              </div>
              <Wallet2 size={36} color="var(--brand-forest)" style={{ marginBottom: '14px' }} />
              <h3 style={{ fontSize: '1.18rem', fontWeight: 800, marginBottom: '10px' }}>1. Member Mobilization</h3>
              <p style={{ color: 'var(--text-muted)', fontSize: '0.9rem', lineHeight: 1.6 }}>
                Members contribute monthly deposits and share capital via daily or monthly MPESA STK push, building a solid pooled fund.
              </p>
              <div style={{ marginTop: '16px', fontSize: '0.78rem', fontWeight: 700, color: 'var(--brand-forest)' }}>
                • Min. KES 1,000 / mo • 100% Guaranteed
              </div>
            </div>

            {/* Step 2 */}
            <div className="benefit-grid-card">
              <div
                style={{
                  width: '48px',
                  height: '48px',
                  borderRadius: '14px',
                  backgroundColor: 'var(--brand-forest)',
                  color: 'var(--brand-lime)',
                  fontSize: '1rem',
                  fontWeight: 800,
                  display: 'flex',
                  alignItems: 'center',
                  justifyContent: 'center',
                  marginBottom: '20px',
                  boxShadow: '0 4px 12px rgba(11, 36, 25, 0.2)',
                }}
              >
                2
              </div>
              <Building2 size={36} color="var(--brand-forest)" style={{ marginBottom: '14px' }} />
              <h3 style={{ fontSize: '1.18rem', fontWeight: 800, marginBottom: '10px' }}>2. Capital Deployment</h3>
              <p style={{ color: 'var(--text-muted)', fontSize: '0.9rem', lineHeight: 1.6 }}>
                Funds are invested into high-yield physical assets: passenger matatu fleets, commercial plazas, agribusiness farms, and petrol stations.
              </p>
              <div style={{ marginTop: '16px', fontSize: '0.78rem', fontWeight: 700, color: 'var(--brand-forest)' }}>
                • 19+ Physical Assets • Fully Insured
              </div>
            </div>

            {/* Step 3 */}
            <div className="benefit-grid-card">
              <div
                style={{
                  width: '48px',
                  height: '48px',
                  borderRadius: '14px',
                  backgroundColor: 'var(--brand-forest)',
                  color: 'var(--brand-lime)',
                  fontSize: '1rem',
                  fontWeight: 800,
                  display: 'flex',
                  alignItems: 'center',
                  justifyContent: 'center',
                  marginBottom: '20px',
                  boxShadow: '0 4px 12px rgba(11, 36, 25, 0.2)',
                }}
              >
                3
              </div>
              <TrendingUp size={36} color="var(--brand-forest)" style={{ marginBottom: '14px' }} />
              <h3 style={{ fontSize: '1.18rem', fontWeight: 800, marginBottom: '10px' }}>3. Daily Cash Flow</h3>
              <p style={{ color: 'var(--text-muted)', fontSize: '0.9rem', lineHeight: 1.6 }}>
                Assets produce recurring revenue every single day through passenger transit fares, tenant commercial rent, and loan interests.
              </p>
              <div style={{ marginTop: '16px', fontSize: '0.78rem', fontWeight: 700, color: 'var(--brand-forest)' }}>
                • Daily Cash Flow • Zero Speculation
              </div>
            </div>

            {/* Step 4 */}
            <div className="benefit-grid-card" style={{ borderColor: 'rgba(226, 179, 74, 0.5)', background: 'linear-gradient(180deg, var(--bg-surface) 0%, rgba(226, 179, 74, 0.04) 100%)' }}>
              <div
                style={{
                  width: '48px',
                  height: '48px',
                  borderRadius: '14px',
                  backgroundColor: 'var(--brand-gold)',
                  color: '#0B2419',
                  fontSize: '1rem',
                  fontWeight: 800,
                  display: 'flex',
                  alignItems: 'center',
                  justifyContent: 'center',
                  marginBottom: '20px',
                  boxShadow: '0 4px 12px rgba(226, 179, 74, 0.3)',
                }}
              >
                4
              </div>
              <PieChart size={36} color="var(--brand-gold)" style={{ marginBottom: '14px' }} />
              <h3 style={{ fontSize: '1.18rem', fontWeight: 800, marginBottom: '10px' }}>4. Dividends & 3x Credit</h3>
              <p style={{ color: 'var(--text-muted)', fontSize: '0.9rem', lineHeight: 1.6 }}>
                Profits are returned directly to members as 14.5% annual dividends, plus up to 3x your savings in instant low-interest credit.
              </p>
              <div style={{ marginTop: '16px', fontSize: '0.78rem', fontWeight: 700, color: 'var(--brand-gold)' }}>
                • 14.5% Annual Dividends • 3x Borrowing
              </div>
            </div>
          </div>
        </section>

        {/* ═════════════════════════════════════════════════════════════════════
            DUAL FINANCIAL CALCULATOR: LOAN & COMPOUND WEALTH PROJECTOR
        ═════════════════════════════════════════════ */}
        <section id="calculator" style={{ backgroundColor: 'var(--surface-2)', padding: '96px 24px', borderTop: '1px solid var(--border-color)', borderBottom: '1px solid var(--border-color)' }}>
          <div style={{ maxWidth: '1160px', margin: '0 auto' }}>
            <div style={{ textAlign: 'center', marginBottom: '40px' }}>
              <span className="eyebrow-pill" style={{ marginBottom: '12px' }}>
                <Calculator size={13} /> Interactive Financial Planner
              </span>
              <h2 style={{ fontSize: '2.4rem', fontWeight: 800, color: 'var(--text-main)', letterSpacing: '-0.5px' }}>
                Calculate Your Financing & Wealth Potential
              </h2>
              <p style={{ color: 'var(--text-muted)', fontSize: '1rem', marginTop: '8px' }}>
                Toggle between our instant loan repayment estimator and compound savings wealth projector.
              </p>

              {/* Calculator Tab Switcher */}
              <div
                style={{
                  display: 'inline-flex',
                  backgroundColor: 'var(--bg-surface)',
                  padding: '6px',
                  borderRadius: '50px',
                  border: '1px solid var(--border-color)',
                  marginTop: '28px',
                  boxShadow: 'var(--shadow-sm)',
                  maxWidth: '100%',
                  overflowX: 'auto',
                }}
              >
                <button
                  onClick={() => setCalcTab('loan')}
                  className={`tab-pill ${calcTab === 'loan' ? 'active' : ''}`}
                >
                  <Banknote size={16} style={{ display: 'inline', marginRight: '6px' }} /> Loan Repayment
                </button>
                <button
                  onClick={() => setCalcTab('savings')}
                  className={`tab-pill ${calcTab === 'savings' ? 'active' : ''}`}
                >
                  <PiggyBank size={16} style={{ display: 'inline', marginRight: '6px' }} /> Compound Wealth
                </button>
              </div>
            </div>

            {/* TAB 1: LOAN CALCULATOR */}
            {calcTab === 'loan' && (
              <div
                className="calculator-card-container"
                style={{
                  backgroundColor: 'var(--bg-surface)',
                  borderRadius: '24px',
                  border: '1px solid var(--border-color)',
                  boxShadow: 'var(--shadow-lg)',
                  padding: '40px',
                  display: 'grid',
                  gridTemplateColumns: 'repeat(auto-fit, minmax(280px, 1fr))',
                  gap: '40px',
                  alignItems: 'start',
                }}
              >
                {/* Sliders & Option Selectors */}
                <div>
                  {/* Loan Type Selector */}
                  <div style={{ marginBottom: '24px' }}>
                    <label className="input-label" style={{ marginBottom: '10px' }}>Select Loan Category</label>
                    <div style={{ display: 'grid', gridTemplateColumns: 'repeat(2, 1fr)', gap: '10px' }}>
                      {(Object.keys(loanRates) as Array<keyof typeof loanRates>).map((k) => (
                        <button
                          key={k}
                          type="button"
                          onClick={() => {
                            setLoanType(k);
                            setLoanAmount(Math.min(Math.max(loanAmount, loanRates[k].minAmt), loanRates[k].maxAmt));
                            setLoanMonths(Math.min(Math.max(loanMonths, loanRates[k].minMos), loanRates[k].maxMos));
                          }}
                          style={{
                            padding: '12px 14px',
                            borderRadius: '12px',
                            border: loanType === k ? '2px solid var(--brand-forest)' : '1px solid var(--border-color)',
                            backgroundColor: loanType === k ? 'var(--brand-lime-soft)' : 'var(--surface-2)',
                            color: loanType === k ? 'var(--brand-forest)' : 'var(--text-main)',
                            fontWeight: loanType === k ? 800 : 600,
                            fontSize: '0.84rem',
                            cursor: 'pointer',
                            textAlign: 'left',
                            transition: 'all 0.15s',
                          }}
                        >
                          <div>{loanRates[k].label.split('(')[0]}</div>
                          <small style={{ fontSize: '0.72rem', color: 'var(--text-muted)' }}>{loanRates[k].label.split('(')[1].replace(')', '')}</small>
                        </button>
                      ))}
                    </div>
                  </div>

                  {/* Loan Amount Slider & Quick Presets */}
                  <div style={{ marginBottom: '26px' }}>
                    <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: '10px' }}>
                      <label className="input-label" style={{ margin: 0 }}>Borrowing Amount</label>
                      <span style={{ fontWeight: 800, color: 'var(--brand-forest)', fontSize: '1.3rem' }}>
                        {formatKES(loanAmount)}
                      </span>
                    </div>
                    <input
                      type="range"
                      min={currentRateObj.minAmt}
                      max={currentRateObj.maxAmt}
                      step={loanAmount > 100000 ? 25000 : 5000}
                      value={loanAmount}
                      onChange={(e) => setLoanAmount(Number(e.target.value))}
                      className="landing-slider"
                    />
                    
                    {/* Quick Amount Presets */}
                    <div style={{ display: 'flex', flexWrap: 'wrap', gap: '6px', marginTop: '10px' }}>
                      {[25000, 50000, 100000, 250000, 500000, 1000000]
                        .filter((amt) => amt >= currentRateObj.minAmt && amt <= currentRateObj.maxAmt)
                        .map((preset) => (
                          <button
                            key={preset}
                            type="button"
                            onClick={() => setLoanAmount(preset)}
                            className={`preset-chip ${loanAmount === preset ? 'active' : ''}`}
                          >
                            {formatKES(preset).replace('.00', '')}
                          </button>
                        ))}
                    </div>
                  </div>

                  {/* Duration Slider & Quick Presets */}
                  <div style={{ marginBottom: '24px' }}>
                    <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: '10px' }}>
                      <label className="input-label" style={{ margin: 0 }}>Repayment Period</label>
                      <span style={{ fontWeight: 800, color: 'var(--brand-forest)', fontSize: '1.3rem' }}>
                        {loanMonths} {loanMonths === 1 ? 'Month' : 'Months'}
                      </span>
                    </div>
                    <input
                      type="range"
                      min={currentRateObj.minMos}
                      max={currentRateObj.maxMos}
                      step={1}
                      value={loanMonths}
                      onChange={(e) => setLoanMonths(Number(e.target.value))}
                      className="landing-slider"
                    />
                    
                    {/* Quick Tenure Presets */}
                    <div style={{ display: 'flex', flexWrap: 'wrap', gap: '6px', marginTop: '10px' }}>
                      {[1, 3, 6, 12, 24, 36, 48]
                        .filter((m) => m >= currentRateObj.minMos && m <= currentRateObj.maxMos)
                        .map((preset) => (
                          <button
                            key={preset}
                            type="button"
                            onClick={() => setLoanMonths(preset)}
                            className={`preset-chip ${loanMonths === preset ? 'active' : ''}`}
                          >
                            {preset} {preset === 1 ? 'Month' : 'Mos'}
                          </button>
                        ))}
                    </div>
                  </div>

                  {/* Toggle Amortization Schedule */}
                  <button
                    type="button"
                    onClick={() => setShowAmortization(!showAmortization)}
                    style={{
                      background: 'none',
                      border: '1px dashed var(--border-color)',
                      padding: '10px 16px',
                      borderRadius: '12px',
                      color: 'var(--brand-forest)',
                      fontWeight: 700,
                      fontSize: '0.85rem',
                      display: 'flex',
                      alignItems: 'center',
                      gap: '8px',
                      cursor: 'pointer',
                      width: '100%',
                      justifyContent: 'center',
                    }}
                  >
                    <Layers size={16} />
                    {showAmortization ? 'Hide Month-by-Month Amortization' : 'View Full Amortization Schedule'}
                  </button>

                  {/* Amortization Table Accordion */}
                  {showAmortization && (
                    <div
                      style={{
                        marginTop: '16px',
                        maxHeight: '260px',
                        overflowY: 'auto',
                        border: '1px solid var(--border-color)',
                        borderRadius: '12px',
                        backgroundColor: 'var(--surface-2)',
                      }}
                    >
                      <table style={{ width: '100%', borderCollapse: 'collapse', fontSize: '0.78rem', textAlign: 'left' }}>
                        <thead style={{ position: 'sticky', top: 0, backgroundColor: 'var(--brand-forest)', color: '#FFFFFF' }}>
                          <tr>
                            <th style={{ padding: '8px 12px' }}>Month</th>
                            <th style={{ padding: '8px 12px' }}>Payment</th>
                            <th style={{ padding: '8px 12px' }}>Interest</th>
                            <th style={{ padding: '8px 12px' }}>Balance</th>
                          </tr>
                        </thead>
                        <tbody>
                          {amortizationSchedule.map((row) => (
                            <tr key={row.month} style={{ borderBottom: '1px solid var(--border-color)' }}>
                              <td style={{ padding: '6px 12px', fontWeight: 700 }}>Mo. {row.month}</td>
                              <td style={{ padding: '6px 12px' }}>{formatKES(row.payment)}</td>
                              <td style={{ padding: '6px 12px', color: 'var(--brand-forest)' }}>{formatKES(row.interest)}</td>
                              <td style={{ padding: '6px 12px', fontWeight: 600 }}>{formatKES(row.remaining)}</td>
                            </tr>
                          ))}
                        </tbody>
                      </table>
                    </div>
                  )}
                </div>

                {/* Calculation Breakdown Card */}
                <div
                  className="glow-card-forest"
                  style={{
                    color: '#FFFFFF',
                    borderRadius: '22px',
                    padding: '34px',
                  }}
                >
                  <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '8px' }}>
                    <span style={{ fontSize: '0.85rem', color: 'rgba(255,255,255,0.7)' }}>Estimated Monthly Installment</span>
                    <span className="badge badge-lime">{loanType === 'express' ? '15 Min Payout' : 'Low Interest'}</span>
                  </div>

                  <div style={{ fontSize: '2.5rem', fontWeight: 800, color: 'var(--brand-lime)', letterSpacing: '-0.8px', marginBottom: '24px' }}>
                    {formatKES(monthlyInstallment)}
                    <span style={{ fontSize: '1rem', color: 'rgba(255,255,255,0.6)', fontWeight: 600 }}> / mo</span>
                  </div>

                  <div style={{ display: 'flex', flexDirection: 'column', gap: '12px', borderTop: '1px solid rgba(255,255,255,0.15)', paddingTop: '20px', marginBottom: '24px' }}>
                    <div style={{ display: 'flex', justifyContent: 'space-between', fontSize: '0.9rem' }}>
                      <span style={{ color: 'rgba(255,255,255,0.7)' }}>Principal Amount:</span>
                      <span style={{ fontWeight: 700 }}>{formatKES(loanAmount)}</span>
                    </div>
                    <div style={{ display: 'flex', justifyContent: 'space-between', fontSize: '0.9rem' }}>
                      <span style={{ color: 'rgba(255,255,255,0.7)' }}>Annual Interest Rate:</span>
                      <span style={{ fontWeight: 700, color: 'var(--brand-lime)' }}>{(currentRateObj.rate * 100).toFixed(1)}% p.a.</span>
                    </div>
                    <div style={{ display: 'flex', justifyContent: 'space-between', fontSize: '0.9rem' }}>
                      <span style={{ color: 'rgba(255,255,255,0.7)' }}>Total Interest Charged:</span>
                      <span style={{ fontWeight: 700, color: 'var(--brand-lime)' }}>{formatKES(totalInterest)}</span>
                    </div>
                    <div style={{ display: 'flex', justifyContent: 'space-between', fontSize: '0.95rem', borderTop: '1px dashed rgba(255,255,255,0.2)', paddingTop: '10px' }}>
                      <span style={{ color: '#FFFFFF', fontWeight: 700 }}>Total Repayment Sum:</span>
                      <span style={{ fontWeight: 800, color: '#FFFFFF' }}>{formatKES(totalRepayable)}</span>
                    </div>
                  </div>

                  <div style={{ display: 'flex', gap: '10px', marginBottom: '14px' }}>
                    <Link
                      href={`/register?type=loan&amount=${loanAmount}&months=${loanMonths}&product=${loanType}`}
                      className="btn btn-lime"
                      style={{ flex: 1, padding: '14px', fontSize: '1rem', justifyContent: 'center' }}
                    >
                      Apply for this Loan <ArrowRight size={16} />
                    </Link>
                    <button
                      type="button"
                      onClick={copyQuote}
                      title="Copy Quote Details"
                      style={{
                        background: 'rgba(255,255,255,0.12)',
                        border: '1px solid rgba(255,255,255,0.25)',
                        borderRadius: '50px',
                        color: '#FFFFFF',
                        padding: '0 16px',
                        display: 'flex',
                        alignItems: 'center',
                        justifyContent: 'center',
                        cursor: 'pointer',
                      }}
                    >
                      {copiedQuote ? <Check size={18} color="var(--brand-lime)" /> : <Copy size={18} />}
                    </button>
                  </div>
                  {copiedQuote && (
                    <div style={{ textAlign: 'center', fontSize: '0.76rem', color: 'var(--brand-lime)' }}>
                      ✓ Loan calculation copied to clipboard!
                    </div>
                  )}
                </div>
              </div>
            )}

            {/* TAB 2: SAVINGS & WEALTH PROJECTOR */}
            {calcTab === 'savings' && (
              <div
                className="calculator-card-container"
                style={{
                  backgroundColor: 'var(--bg-surface)',
                  borderRadius: '24px',
                  border: '1px solid var(--border-color)',
                  boxShadow: 'var(--shadow-lg)',
                  padding: '40px',
                  display: 'grid',
                  gridTemplateColumns: 'repeat(auto-fit, minmax(280px, 1fr))',
                  gap: '40px',
                  alignItems: 'start',
                }}
              >
                {/* Sliders & Presets */}
                <div>
                  <div style={{ marginBottom: '26px' }}>
                    <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: '10px' }}>
                      <label className="input-label" style={{ margin: 0 }}>Monthly Deposit Amount</label>
                      <span style={{ fontWeight: 800, color: 'var(--brand-forest)', fontSize: '1.3rem' }}>
                        {formatKES(monthlySavings)}
                      </span>
                    </div>
                    <input
                      type="range"
                      min={1000}
                      max={50000}
                      step={1000}
                      value={monthlySavings}
                      onChange={(e) => setMonthlySavings(Number(e.target.value))}
                      className="landing-slider"
                    />
                    
                    {/* Quick Monthly Presets */}
                    <div style={{ display: 'flex', flexWrap: 'wrap', gap: '6px', marginTop: '10px' }}>
                      {[2000, 5000, 10000, 20000, 50000].map((preset) => (
                        <button
                          key={preset}
                          type="button"
                          onClick={() => setMonthlySavings(preset)}
                          className={`preset-chip ${monthlySavings === preset ? 'active' : ''}`}
                        >
                          {formatKES(preset).replace('.00', '')}
                        </button>
                      ))}
                    </div>
                  </div>

                  <div style={{ marginBottom: '26px' }}>
                    <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: '10px' }}>
                      <label className="input-label" style={{ margin: 0 }}>Investment Horizon</label>
                      <span style={{ fontWeight: 800, color: 'var(--brand-forest)', fontSize: '1.3rem' }}>
                        {savingsYears} {savingsYears === 1 ? 'Year' : 'Years'}
                      </span>
                    </div>
                    <input
                      type="range"
                      min={1}
                      max={10}
                      step={1}
                      value={savingsYears}
                      onChange={(e) => setSavingsYears(Number(e.target.value))}
                      className="landing-slider"
                    />
                    
                    {/* Quick Year Presets */}
                    <div style={{ display: 'flex', flexWrap: 'wrap', gap: '6px', marginTop: '10px' }}>
                      {[1, 2, 3, 5, 10].map((preset) => (
                        <button
                          key={preset}
                          type="button"
                          onClick={() => setSavingsYears(preset)}
                          className={`preset-chip ${savingsYears === preset ? 'active' : ''}`}
                        >
                          {preset} {preset === 1 ? 'Year' : 'Years'}
                        </button>
                      ))}
                    </div>
                  </div>

                  {/* Growth Milestone Cards Grid */}
                  <div style={{ marginTop: '24px' }}>
                    <label className="input-label" style={{ marginBottom: '10px' }}>Projected Growth Milestones</label>
                    <div style={{ display: 'grid', gridTemplateColumns: 'repeat(2, 1fr)', gap: '10px' }}>
                      <div style={{ backgroundColor: 'var(--surface-2)', padding: '12px', borderRadius: '12px', border: '1px solid var(--border-color)' }}>
                        <div style={{ fontSize: '0.72rem', color: 'var(--text-muted)', textTransform: 'uppercase' }}>Year 1 Balance</div>
                        <div style={{ fontWeight: 800, color: 'var(--brand-forest)', fontSize: '0.95rem', marginTop: '2px' }}>{formatKES(savingsProjection.y1)}</div>
                      </div>
                      <div style={{ backgroundColor: 'var(--surface-2)', padding: '12px', borderRadius: '12px', border: '1px solid var(--border-color)' }}>
                        <div style={{ fontSize: '0.72rem', color: 'var(--text-muted)', textTransform: 'uppercase' }}>Year 3 Balance</div>
                        <div style={{ fontWeight: 800, color: 'var(--brand-forest)', fontSize: '0.95rem', marginTop: '2px' }}>{formatKES(savingsProjection.y3)}</div>
                      </div>
                      <div style={{ backgroundColor: 'var(--surface-2)', padding: '12px', borderRadius: '12px', border: '1px solid var(--border-color)' }}>
                        <div style={{ fontSize: '0.72rem', color: 'var(--text-muted)', textTransform: 'uppercase' }}>Year 5 Balance</div>
                        <div style={{ fontWeight: 800, color: 'var(--brand-forest)', fontSize: '0.95rem', marginTop: '2px' }}>{formatKES(savingsProjection.y5)}</div>
                      </div>
                      <div style={{ backgroundColor: 'var(--surface-2)', padding: '12px', borderRadius: '12px', border: '1px solid var(--brand-forest)' }}>
                        <div style={{ fontSize: '0.72rem', color: 'var(--brand-forest)', textTransform: 'uppercase', fontWeight: 700 }}>Year 10 Wealth</div>
                        <div style={{ fontWeight: 800, color: 'var(--brand-forest)', fontSize: '0.95rem', marginTop: '2px' }}>{formatKES(savingsProjection.y10)}</div>
                      </div>
                    </div>
                  </div>
                </div>

                {/* Growth Outcome Card */}
                <div
                  className="glow-card-forest"
                  style={{
                    color: '#FFFFFF',
                    borderRadius: '22px',
                    padding: '34px',
                  }}
                >
                  <div style={{ fontSize: '0.85rem', color: 'rgba(255,255,255,0.7)', marginBottom: '4px' }}>
                    Projected Future Accumulated Wealth
                  </div>
                  <div style={{ fontSize: '2.5rem', fontWeight: 800, color: 'var(--brand-lime)', letterSpacing: '-0.8px', marginBottom: '24px' }}>
                    {formatKES(savingsProjection.finalBalance)}
                  </div>

                  {/* Visual Bar Comparison */}
                  <div style={{ marginBottom: '22px' }}>
                    <div style={{ display: 'flex', justifyContent: 'space-between', fontSize: '0.75rem', marginBottom: '6px' }}>
                      <span style={{ color: 'rgba(255,255,255,0.8)' }}>Deposits: {formatKES(savingsProjection.totalDeposited)}</span>
                      <span style={{ color: 'var(--brand-lime)', fontWeight: 700 }}>Dividends: +{formatKES(savingsProjection.totalDividendsEarned)}</span>
                    </div>
                    <div style={{ height: '10px', width: '100%', backgroundColor: 'rgba(255,255,255,0.15)', borderRadius: '5px', overflow: 'hidden', display: 'flex' }}>
                      <div
                        style={{
                          width: `${Math.min(100, (savingsProjection.totalDeposited / savingsProjection.finalBalance) * 100)}%`,
                          backgroundColor: '#FFFFFF',
                        }}
                      />
                      <div
                        style={{
                          width: `${Math.min(100, (savingsProjection.totalDividendsEarned / savingsProjection.finalBalance) * 100)}%`,
                          backgroundColor: 'var(--brand-lime)',
                        }}
                      />
                    </div>
                  </div>

                  <div style={{ display: 'flex', flexDirection: 'column', gap: '12px', borderTop: '1px solid rgba(255,255,255,0.15)', paddingTop: '20px', marginBottom: '28px' }}>
                    <div style={{ display: 'flex', justifyContent: 'space-between', fontSize: '0.9rem' }}>
                      <span style={{ color: 'rgba(255,255,255,0.7)' }}>Your Total Cash Contributed:</span>
                      <span style={{ fontWeight: 700 }}>{formatKES(savingsProjection.totalDeposited)}</span>
                    </div>
                    <div style={{ display: 'flex', justifyContent: 'space-between', fontSize: '0.9rem' }}>
                      <span style={{ color: 'rgba(255,255,255,0.7)' }}>Pure Dividend Profit Earned:</span>
                      <span style={{ fontWeight: 800, color: 'var(--brand-lime)' }}>+{formatKES(savingsProjection.totalDividendsEarned)}</span>
                    </div>
                    <div style={{ display: 'flex', justifyContent: 'space-between', fontSize: '0.9rem' }}>
                      <span style={{ color: 'rgba(255,255,255,0.7)' }}>3x Loan Borrowing Power:</span>
                      <span style={{ fontWeight: 800, color: 'var(--brand-gold)' }}>{formatKES(savingsProjection.loanBorrowingCapacity)}</span>
                    </div>
                  </div>

                  <Link href="/register" className="btn btn-lime" style={{ width: '100%', padding: '14px', fontSize: '1rem', justifyContent: 'center' }}>
                    Start Building Wealth Today <ArrowRight size={16} />
                  </Link>
                </div>
              </div>
            )}
          </div>
        </section>

        {/* ═════════════════════════════════════════════════════════════════════
            PRODUCT ECOSYSTEM & FINANCIAL SOLUTIONS
        ═════════════════════════════════════════════ */}
        <section id="services" style={{ padding: '96px 24px', maxWidth: '1240px', margin: '0 auto' }}>
          <div style={{ textAlign: 'center', marginBottom: '48px' }}>
            <span className="eyebrow-pill" style={{ marginBottom: '14px' }}>
              <Coins size={13} /> Sacco Product Suite
            </span>
            <h2 style={{ fontSize: '2.4rem', fontWeight: 800, color: 'var(--text-main)', letterSpacing: '-0.5px' }}>
              Tailored Financial Products for Transport Professionals
            </h2>
            <p style={{ color: 'var(--text-muted)', fontSize: '1rem', maxWidth: '600px', margin: '12px auto 0' }}>
              From 15-minute emergency mobile advances to PSV matatu ownership and welfare protection.
            </p>

            {/* Product Category Filters */}
            <div style={{ display: 'flex', justifyContent: 'center', flexWrap: 'wrap', gap: '10px', marginTop: '28px' }}>
              {[
                { id: 'all', label: 'All Products' },
                { id: 'loans', label: 'Credit & Loans' },
                { id: 'savings', label: 'Savings & Shares' },
                { id: 'welfare', label: 'Welfare & Solidarity' },
              ].map((cat) => (
                <button
                  key={cat.id}
                  onClick={() => setProductCategory(cat.id)}
                  className={`filter-chip ${productCategory === cat.id ? 'active' : ''}`}
                >
                  {cat.label}
                </button>
              ))}
            </div>
          </div>

          {/* Product Cards Grid */}
          <div
            style={{
              display: 'grid',
              gridTemplateColumns: 'repeat(auto-fit, minmax(340px, 1fr))',
              gap: '28px',
            }}
          >
            {filteredProducts.map((prod) => (
              <div
                key={prod.id}
                className="benefit-grid-card"
                style={{
                  display: 'flex',
                  flexDirection: 'column',
                  justifyContent: 'space-between',
                  cursor: 'pointer',
                }}
                onClick={() => setSelectedProduct(prod)}
              >
                <div>
                  <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', marginBottom: '16px' }}>
                    <span className="badge badge-lime">{prod.badge}</span>
                    <span style={{ fontSize: '0.78rem', fontWeight: 700, color: 'var(--brand-forest)', display: 'flex', alignItems: 'center', gap: '4px' }}>
                      <Clock size={13} /> {prod.speed}
                    </span>
                  </div>

                  <h3 style={{ fontSize: '1.25rem', fontWeight: 800, color: 'var(--text-main)', marginBottom: '10px' }}>
                    {prod.name}
                  </h3>

                  <p style={{ color: 'var(--text-muted)', fontSize: '0.88rem', lineHeight: 1.6, marginBottom: '20px' }}>
                    {prod.description}
                  </p>

                  <div style={{ backgroundColor: 'var(--surface-2)', borderRadius: '12px', padding: '14px', marginBottom: '20px' }}>
                    <div style={{ display: 'flex', justifyContent: 'space-between', fontSize: '0.85rem', marginBottom: '6px' }}>
                      <span style={{ color: 'var(--text-muted)' }}>Rate / Benefit:</span>
                      <b style={{ color: 'var(--brand-forest)' }}>{prod.interestRate}</b>
                    </div>
                    <div style={{ display: 'flex', justifyContent: 'space-between', fontSize: '0.85rem' }}>
                      <span style={{ color: 'var(--text-muted)' }}>Max Limit:</span>
                      <b style={{ color: 'var(--brand-forest)' }}>{prod.maxAmount}</b>
                    </div>
                  </div>

                  <ul style={{ listStyle: 'none', display: 'flex', flexDirection: 'column', gap: '8px', marginBottom: '24px' }}>
                    {prod.features.slice(0, 3).map((feat, idx) => (
                      <li key={idx} style={{ fontSize: '0.84rem', color: 'var(--text-main)', display: 'flex', alignItems: 'center', gap: '8px' }}>
                        <CheckCircle2 size={15} color="#16a34a" style={{ flexShrink: 0 }} /> {feat}
                      </li>
                    ))}
                  </ul>
                </div>

                <button
                  type="button"
                  className="btn btn-outline-forest"
                  style={{ width: '100%', justifyContent: 'center' }}
                  onClick={(e) => {
                    e.stopPropagation();
                    setSelectedProduct(prod);
                  }}
                >
                  View Details & Requirements <ArrowRight size={15} />
                </button>
              </div>
            ))}
          </div>
        </section>

        {/* ═════════════════════════════════════════════════════════════════════
            FILTERABLE ASSET PORTFOLIO GALLERY (19 SACCO ASSETS)
        ═════════════════════════════════════════════ */}
        <section id="portfolio" style={{ backgroundColor: 'var(--surface-2)', padding: '96px 24px', borderTop: '1px solid var(--border-color)', borderBottom: '1px solid var(--border-color)' }}>
          <div style={{ maxWidth: '1240px', margin: '0 auto' }}>
            <div style={{ textAlign: 'center', marginBottom: '44px' }}>
              <span className="eyebrow-pill" style={{ marginBottom: '14px' }}>
                <Building2 size={13} /> Tangible Asset Portfolio
              </span>
              <h2 style={{ fontSize: '2.4rem', fontWeight: 800, color: 'var(--text-main)' }}>
                Real Cooperative Assets Generating Daily Member Wealth
              </h2>
              <p style={{ color: 'var(--text-muted)', fontSize: '1rem', maxWidth: '640px', margin: '12px auto 0' }}>
                Every single shilling you contribute is backed by real, income-generating physical property across Kenya.
              </p>

              {/* Asset Search & Filter Controls */}
              <div style={{ maxWidth: '520px', margin: '24px auto 0', position: 'relative' }}>
                <Search size={18} style={{ position: 'absolute', left: '16px', top: '50%', transform: 'translateY(-50%)', color: 'var(--text-muted)' }} />
                <input
                  type="text"
                  placeholder="Filter 19 assets by name, location, or crop..."
                  value={portfolioSearch}
                  onChange={(e) => setPortfolioSearch(e.target.value)}
                  style={{
                    width: '100%',
                    padding: '12px 18px 12px 46px',
                    borderRadius: '50px',
                    border: '1px solid var(--border-color)',
                    backgroundColor: 'var(--bg-surface)',
                    color: 'var(--text-main)',
                    outline: 'none',
                    fontSize: '0.9rem',
                  }}
                />
                {portfolioSearch && (
                  <button
                    onClick={() => setPortfolioSearch('')}
                    style={{
                      position: 'absolute',
                      right: '14px',
                      top: '50%',
                      transform: 'translateY(-50%)',
                      background: 'none',
                      border: 'none',
                      cursor: 'pointer',
                      color: 'var(--text-muted)',
                    }}
                  >
                    <X size={16} />
                  </button>
                )}
              </div>

              {/* Portfolio Filter Pills */}
              <div style={{ display: 'flex', justifyContent: 'center', flexWrap: 'wrap', gap: '10px', marginTop: '20px' }}>
                {[
                  { id: 'all', label: `All Assets (${totalSlides})` },
                  { id: 'agri', label: 'Agribusiness & Crops' },
                  { id: 'real-estate', label: 'Commercial Real Estate' },
                  { id: 'fuel', label: 'Fueling Stations' },
                  { id: 'fleet', label: 'Machinery & Transit' },
                  { id: 'welfare', label: 'Welfare Assets' },
                ].map((pill) => (
                  <button
                    key={pill.id}
                    onClick={() => setPortfolioCategory(pill.id)}
                    className={`filter-chip ${portfolioCategory === pill.id ? 'active' : ''}`}
                  >
                    {pill.label}
                  </button>
                ))}
              </div>
            </div>

            {/* Assets Grid */}
            <div
              style={{
                display: 'grid',
                gridTemplateColumns: 'repeat(auto-fit, minmax(280px, 1fr))',
                gap: '24px',
              }}
            >
              {filteredAssets.map((asset) => {
                const globalIndex = SACCO_ASSETS.findIndex((a) => a.id === asset.id);
                return (
                  <div
                    key={asset.id}
                    className="gallery-card"
                    onClick={() => setSelectedAssetIndex(globalIndex)}
                    style={{ cursor: 'pointer' }}
                  >
                    <div style={{ height: '210px', position: 'relative', overflow: 'hidden' }}>
                      <img
                        src={asset.image}
                        alt={asset.title}
                        className="img-zoom"
                        style={{ width: '100%', height: '100%', objectFit: 'cover', display: 'block' }}
                      />
                      <div style={{ position: 'absolute', top: '12px', left: '12px' }}>
                        <span
                          style={{
                            backgroundColor: 'rgba(7, 20, 15, 0.85)',
                            backdropFilter: 'blur(6px)',
                            color: 'var(--brand-lime)',
                            fontSize: '0.7rem',
                            fontWeight: 800,
                            padding: '4px 10px',
                            borderRadius: '50px',
                          }}
                        >
                          {asset.categoryLabel}
                        </span>
                      </div>
                      <div style={{ position: 'absolute', bottom: '10px', right: '12px' }}>
                        <span
                          style={{
                            backgroundColor: 'rgba(7, 20, 15, 0.85)',
                            color: '#FFFFFF',
                            fontSize: '0.72rem',
                            fontWeight: 700,
                            padding: '3px 8px',
                            borderRadius: '6px',
                          }}
                        >
                          Val: {asset.valuation}
                        </span>
                      </div>
                    </div>

                    <div style={{ padding: '20px' }}>
                      <h3 style={{ fontSize: '1.05rem', fontWeight: 800, color: 'var(--text-main)', marginBottom: '8px', lineHeight: 1.3 }}>
                        {asset.title}
                      </h3>
                      <p style={{ color: 'var(--text-muted)', fontSize: '0.82rem', lineHeight: 1.5, marginBottom: '14px' }}>
                        {asset.description}
                      </p>
                      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', borderTop: '1px solid var(--border-subtle)', paddingTop: '12px', fontSize: '0.8rem' }}>
                        <span style={{ color: 'var(--text-dim)' }}>{asset.location}</span>
                        <span style={{ color: 'var(--brand-forest)', fontWeight: 800, display: 'flex', alignItems: 'center', gap: '3px' }}>
                          Yield: {asset.annualRoi}
                        </span>
                      </div>
                    </div>
                  </div>
                );
              })}
            </div>
          </div>
        </section>

        {/* ═════════════════════════════════════════════════════════════════════
            COMPARISON TABLE: SACCO VS COMMERCIAL BANKS
        ═════════════════════════════════════════════ */}
        <section style={{ padding: '96px 24px', maxWidth: '1080px', margin: '0 auto' }}>
          <div style={{ textAlign: 'center', marginBottom: '50px' }}>
            <span className="eyebrow-pill" style={{ marginBottom: '12px' }}>
              <Scale size={13} /> The Cooperative Advantage
            </span>
            <h2 style={{ fontSize: '2.4rem', fontWeight: 800, color: 'var(--text-main)' }}>
              Why Drivers Choose Umoja Sacco Over Banks
            </h2>
            <p style={{ color: 'var(--text-muted)', fontSize: '1rem', marginTop: '8px' }}>
              Commercial banks treat you as a customer. At Umoja Sacco, you are an equal owner and dividend shareholder.
            </p>
          </div>

          <div className="table-container" style={{ boxShadow: 'var(--shadow-md)' }}>
            <table className="data-table">
              <thead>
                <tr>
                  <th style={{ width: '30%' }}>Comparison Factor</th>
                  <th style={{ width: '35%', backgroundColor: '#0B2419', color: 'var(--brand-lime)' }}>
                    ✨ Umoja Drivers Sacco
                  </th>
                  <th style={{ width: '35%', backgroundColor: '#334155' }}>Commercial Banks</th>
                </tr>
              </thead>
              <tbody>
                <tr>
                  <td><b>Ownership & Profits</b></td>
                  <td style={{ color: 'var(--brand-forest)', fontWeight: 700 }}>
                    <CheckCircle2 size={16} color="#16a34a" style={{ display: 'inline', marginRight: '6px' }} />
                    You are a Shareholder (Earn 14.5% Dividends)
                  </td>
                  <td style={{ color: 'var(--text-muted)' }}>You are a customer (Bank keeps 100% profit)</td>
                </tr>
                <tr>
                  <td><b>Loan Interest Rates</b></td>
                  <td style={{ color: 'var(--brand-forest)', fontWeight: 700 }}>
                    <CheckCircle2 size={16} color="#16a34a" style={{ display: 'inline', marginRight: '6px' }} />
                    Affordable 8% - 12% p.a. Reducing Balance
                  </td>
                  <td style={{ color: 'var(--text-muted)' }}>High 18% - 24% p.a. + endless hidden ledger fees</td>
                </tr>
                <tr>
                  <td><b>Mobile Loan Speed</b></td>
                  <td style={{ color: 'var(--brand-forest)', fontWeight: 700 }}>
                    <CheckCircle2 size={16} color="#16a34a" style={{ display: 'inline', marginRight: '6px' }} />
                    15 Minutes via automated MPESA
                  </td>
                  <td style={{ color: 'var(--text-muted)' }}>Days of branch queues and paper bureaucracy</td>
                </tr>
                <tr>
                  <td><b>Borrowing Power Multiplier</b></td>
                  <td style={{ color: 'var(--brand-forest)', fontWeight: 700 }}>
                    <CheckCircle2 size={16} color="#16a34a" style={{ display: 'inline', marginRight: '6px' }} />
                    Up to 3x your cumulative savings
                  </td>
                  <td style={{ color: 'var(--text-muted)' }}>Strict credit rating algorithms and high rejection</td>
                </tr>
                <tr>
                  <td><b>Emergency & Family Welfare Shield</b></td>
                  <td style={{ color: 'var(--brand-forest)', fontWeight: 700 }}>
                    <CheckCircle2 size={16} color="#16a34a" style={{ display: 'inline', marginRight: '6px' }} />
                    Hospital cash grants & bereavement relief included
                  </td>
                  <td style={{ color: 'var(--text-muted)' }}>Zero welfare assistance during personal distress</td>
                </tr>
                <tr>
                  <td><b>Regulatory Protection</b></td>
                  <td style={{ color: 'var(--brand-forest)', fontWeight: 700 }}>
                    <CheckCircle2 size={16} color="#16a34a" style={{ display: 'inline', marginRight: '6px' }} />
                    SASRA Regulated & Cooperative Audited
                  </td>
                  <td style={{ color: 'var(--text-muted)' }}>Central Bank of Kenya regulated</td>
                </tr>
              </tbody>
            </table>
          </div>
        </section>

        {/* ═════════════════════════════════════════════════════════════════════
            MEMBER TESTIMONIALS & REAL COMMUNITY STORIES
        ═════════════════════════════════════════════ */}
        <section style={{ backgroundColor: 'var(--surface-2)', padding: '96px 24px', borderTop: '1px solid var(--border-color)', borderBottom: '1px solid var(--border-color)' }}>
          <div style={{ maxWidth: '1240px', margin: '0 auto' }}>
            <div style={{ textAlign: 'center', marginBottom: '56px' }}>
              <span className="eyebrow-pill" style={{ marginBottom: '14px' }}>
                <Users size={13} /> Member Success Stories
              </span>
              <h2 style={{ fontSize: '2.4rem', fontWeight: 800, color: 'var(--text-main)' }}>
                Real Stories From Transport Professionals
              </h2>
              <p style={{ color: 'var(--text-muted)', fontSize: '1rem', maxWidth: '600px', margin: '12px auto 0' }}>
                Join over 12,400 members who transformed their livelihoods through collective co-operative ownership.
              </p>
            </div>

            <div
              style={{
                display: 'grid',
                gridTemplateColumns: 'repeat(auto-fit, minmax(320px, 1fr))',
                gap: '28px',
              }}
            >
              {/* Testimonial 1 */}
              <div className="benefit-grid-card">
                <div style={{ display: 'flex', gap: '4px', color: '#E2B34A', marginBottom: '14px' }}>
                  {Array.from({ length: 5 }).map((_, i) => (
                    <span key={i} style={{ fontSize: '1.1rem' }}>★</span>
                  ))}
                </div>
                <p style={{ fontStyle: 'italic', color: 'var(--text-main)', fontSize: '0.94rem', lineHeight: 1.7, marginBottom: '20px' }}>
                  "I was an ordinary matatu driver for 8 years. Through Umoja Sacco's asset financing, I deposited 20% and acquired my first 33-seater bus. Today, I own 3 buses on the Thika superhighway route and earn over KES 140,000 in annual dividends."
                </p>
                <div style={{ display: 'flex', alignItems: 'center', gap: '14px', borderTop: '1px solid var(--border-color)', paddingTop: '16px' }}>
                  <div
                    style={{
                      width: '46px',
                      height: '46px',
                      borderRadius: '50%',
                      backgroundColor: 'var(--brand-forest)',
                      color: 'var(--brand-lime)',
                      fontWeight: 800,
                      display: 'flex',
                      alignItems: 'center',
                      justifyContent: 'center',
                      fontSize: '1rem',
                    }}
                  >
                    JK
                  </div>
                  <div>
                    <div style={{ fontWeight: 800, fontSize: '0.95rem', color: 'var(--text-main)' }}>Josephat Kamau</div>
                    <div style={{ fontSize: '0.78rem', color: 'var(--text-muted)' }}>Fleet Owner & Member since 2019 • Thika Route</div>
                  </div>
                </div>
              </div>

              {/* Testimonial 2 */}
              <div className="benefit-grid-card">
                <div style={{ display: 'flex', gap: '4px', color: '#E2B34A', marginBottom: '14px' }}>
                  {Array.from({ length: 5 }).map((_, i) => (
                    <span key={i} style={{ fontSize: '1.1rem' }}>★</span>
                  ))}
                </div>
                <p style={{ fontStyle: 'italic', color: 'var(--text-main)', fontSize: '0.94rem', lineHeight: 1.7, marginBottom: '20px' }}>
                  "When my daughter was admitted to secondary school, commercial banks asked for title deeds I didn't have. Umoja Sacco approved my KES 85,000 education loan in 15 minutes on M-Pesa at only 6% interest. That is genuine empowerment."
                </p>
                <div style={{ display: 'flex', alignItems: 'center', gap: '14px', borderTop: '1px solid var(--border-color)', paddingTop: '16px' }}>
                  <div
                    style={{
                      width: '46px',
                      height: '46px',
                      borderRadius: '50%',
                      backgroundColor: 'var(--brand-forest)',
                      color: 'var(--brand-lime)',
                      fontWeight: 800,
                      display: 'flex',
                      alignItems: 'center',
                      justifyContent: 'center',
                      fontSize: '1rem',
                    }}
                  >
                    MW
                  </div>
                  <div>
                    <div style={{ fontWeight: 800, fontSize: '0.95rem', color: 'var(--text-main)' }}>Mercy Wanjiku</div>
                    <div style={{ fontSize: '0.78rem', color: 'var(--text-muted)' }}>Courier Delivery Operator • Nairobi Metro</div>
                  </div>
                </div>
              </div>

              {/* Testimonial 3 */}
              <div className="benefit-grid-card">
                <div style={{ display: 'flex', gap: '4px', color: '#E2B34A', marginBottom: '14px' }}>
                  {Array.from({ length: 5 }).map((_, i) => (
                    <span key={i} style={{ fontSize: '1.1rem' }}>★</span>
                  ))}
                </div>
                <p style={{ fontStyle: 'italic', color: 'var(--text-main)', fontSize: '0.94rem', lineHeight: 1.7, marginBottom: '20px' }}>
                  "I was involved in a road collision in 2023. The Umoja Welfare Fund stepped in immediately with KES 120,000 for hospital medical clearance without asking me to touch my core savings. I will never leave this Sacco."
                </p>
                <div style={{ display: 'flex', alignItems: 'center', gap: '14px', borderTop: '1px solid var(--border-color)', paddingTop: '16px' }}>
                  <div
                    style={{
                      width: '46px',
                      height: '46px',
                      borderRadius: '50%',
                      backgroundColor: 'var(--brand-forest)',
                      color: 'var(--brand-lime)',
                      fontWeight: 800,
                      display: 'flex',
                      alignItems: 'center',
                      justifyContent: 'center',
                      fontSize: '1rem',
                    }}
                  >
                    PO
                  </div>
                  <div>
                    <div style={{ fontWeight: 800, fontSize: '0.95rem', color: 'var(--text-main)' }}>Peter Otieno</div>
                    <div style={{ fontSize: '0.78rem', color: 'var(--text-muted)' }}>Long Distance Bus Captain • Eldoret Highway</div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </section>

        {/* ═════════════════════════════════════════════════════════════════════
            INTERACTIVE FAQS WITH SEARCH & CATEGORY CHIPS
        ═════════════════════════════════════════════ */}
        <section id="faqs" style={{ padding: '96px 24px', maxWidth: '880px', margin: '0 auto' }}>
          <div style={{ textAlign: 'center', marginBottom: '44px' }}>
            <span className="eyebrow-pill" style={{ marginBottom: '12px' }}>Got Questions?</span>
            <h2 style={{ fontSize: '2.4rem', fontWeight: 800, color: 'var(--text-main)' }}>
              Frequently Asked Questions
            </h2>
            <p style={{ color: 'var(--text-muted)', fontSize: '1rem', marginTop: '8px' }}>
              Everything you need to know about joining, borrowing, dividends, and welfare protection.
            </p>

            {/* FAQ Search Box */}
            <div style={{ position: 'relative', maxWidth: '520px', margin: '24px auto 0' }}>
              <Search size={18} style={{ position: 'absolute', left: '16px', top: '50%', transform: 'translateY(-50%)', color: 'var(--text-muted)' }} />
              <input
                type="text"
                placeholder="Search questions (e.g. loans, dividends, requirements)..."
                value={faqSearch}
                onChange={(e) => setFaqSearch(e.target.value)}
                style={{
                  width: '100%',
                  padding: '12px 18px 12px 46px',
                  borderRadius: '50px',
                  border: '1px solid var(--border-color)',
                  backgroundColor: 'var(--bg-surface)',
                  color: 'var(--text-main)',
                  outline: 'none',
                  fontSize: '0.92rem',
                }}
              />
              {faqSearch && (
                <button
                  onClick={() => setFaqSearch('')}
                  style={{
                    position: 'absolute',
                    right: '14px',
                    top: '50%',
                    transform: 'translateY(-50%)',
                    background: 'none',
                    border: 'none',
                    cursor: 'pointer',
                    color: 'var(--text-muted)',
                  }}
                >
                  <X size={16} />
                </button>
              )}
            </div>

            {/* Category Chips */}
            <div style={{ display: 'flex', justifyContent: 'center', flexWrap: 'wrap', gap: '8px', marginTop: '16px' }}>
              {[
                { id: 'all', label: 'All Topics' },
                { id: 'membership', label: 'Membership' },
                { id: 'loans', label: 'Loans & Rates' },
                { id: 'savings', label: 'Savings & Dividends' },
                { id: 'welfare', label: 'Welfare Fund' },
                { id: 'security', label: 'SASRA Security' },
              ].map((chip) => (
                <button
                  key={chip.id}
                  onClick={() => setFaqCategory(chip.id)}
                  className={`filter-chip ${faqCategory === chip.id ? 'active' : ''}`}
                  style={{ padding: '6px 14px', fontSize: '0.78rem' }}
                >
                  {chip.label}
                </button>
              ))}
            </div>
          </div>

          {/* Accordion list */}
          <div style={{ display: 'flex', flexDirection: 'column', gap: '14px' }}>
            {filteredFaqs.length === 0 ? (
              <div style={{ textAlign: 'center', padding: '40px', color: 'var(--text-muted)' }}>
                No matching questions found. Try another search term or contact our support desk.
              </div>
            ) : (
              filteredFaqs.map((faq, idx) => {
                const isOpen = openFaq === idx;
                return (
                  <div
                    key={idx}
                    style={{
                      backgroundColor: 'var(--bg-surface)',
                      borderRadius: '18px',
                      border: isOpen ? '1px solid var(--brand-forest)' : '1px solid var(--border-color)',
                      overflow: 'hidden',
                      transition: 'all 0.2s ease',
                      boxShadow: isOpen ? 'var(--shadow-sm)' : 'none',
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
                      <span style={{ paddingRight: '12px' }}>{faq.q}</span>
                      <ChevronDown
                        size={20}
                        style={{
                          color: isOpen ? 'var(--brand-forest)' : 'var(--text-muted)',
                          transform: isOpen ? 'rotate(180deg)' : 'rotate(0)',
                          transition: 'transform 0.25s ease',
                          flexShrink: 0,
                        }}
                      />
                    </button>
                    {isOpen && (
                      <div
                        style={{
                          padding: '0 24px 22px',
                          color: 'var(--text-muted)',
                          fontSize: '0.94rem',
                          lineHeight: 1.65,
                          borderTop: '1px solid var(--border-subtle)',
                          paddingTop: '16px',
                        }}
                      >
                        {faq.a}
                      </div>
                    )}
                  </div>
                );
              })
            )}
          </div>
        </section>

        {/* ═════════════════════════════════════════════════════════════════════
            HIGH-CONVERSION CTA BANNER
        ═════════════════════════════════════════════ */}
        <section
          style={{
            background: 'linear-gradient(145deg, #07140F 0%, #0B2419 50%, #0F392B 100%)',
            color: '#FFFFFF',
            padding: '90px 24px',
            textAlign: 'center',
            position: 'relative',
            overflow: 'hidden',
          }}
        >
          {/* Ambient Orb */}
          <div
            style={{
              position: 'absolute',
              top: '50%',
              left: '50%',
              transform: 'translate(-50%, -50%)',
              width: '600px',
              height: '400px',
              background: 'radial-gradient(ellipse, rgba(163, 230, 53, 0.12) 0%, transparent 70%)',
              borderRadius: '50%',
              pointerEvents: 'none',
            }}
          />

          <div style={{ maxWidth: '780px', margin: '0 auto', position: 'relative', zIndex: 2 }}>
            <span className="eyebrow-pill" style={{ marginBottom: '18px' }}>
              <Zap size={13} /> Quick Member Registration
            </span>

            <h2 style={{ fontSize: 'clamp(2.3rem, 4.5vw, 3.2rem)', fontWeight: 800, marginBottom: '18px', letterSpacing: '-0.8px', lineHeight: 1.15 }}>
              Stop Waiting on Banks. <br />
              <span className="gradient-text-lime">Start Owning Real Assets Today.</span>
            </h2>

            <p style={{ color: 'rgba(255, 255, 255, 0.8)', fontSize: '1.08rem', marginBottom: '36px', lineHeight: 1.7 }}>
              Create your account in under 3 minutes with your National ID and phone number. Start saving, access 15-minute MPESA loans, and earn annual 14.5% dividends.
            </p>

            {/* Quick 3-Step checklist */}
            <div
              style={{
                display: 'flex',
                justifyContent: 'center',
                flexWrap: 'wrap',
                gap: '24px',
                marginBottom: '40px',
                fontSize: '0.86rem',
                color: 'rgba(255, 255, 255, 0.85)',
              }}
            >
              <span style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
                <CheckCircle2 size={16} color="var(--brand-lime)" /> 1. National ID & Phone
              </span>
              <span style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
                <CheckCircle2 size={16} color="var(--brand-lime)" /> 2. KES 1,000 Share Capital
              </span>
              <span style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
                <CheckCircle2 size={16} color="var(--brand-lime)" /> 3. Instant 3x Credit & Dividends
              </span>
            </div>

            <div style={{ display: 'flex', justifyContent: 'center', gap: '16px', flexWrap: 'wrap' }}>
              <Link href="/register" className="btn btn-lime btn-lg" style={{ boxShadow: '0 8px 24px rgba(163, 230, 53, 0.45)' }}>
                Create Member Account <ArrowRight size={18} />
              </Link>
              <Link href="/login" className="btn btn-outline-lime btn-lg">
                Member Portal Sign In
              </Link>
            </div>
          </div>
        </section>
      </main>

      {/* ═════════════════════════════════════════════════════════════════════
          ASSET DETAIL MODAL / LIGHTBOX (WITH INTERACTIVE PREV / NEXT)
      ═════════════════════════════════════════════ */}
      {selectedAssetIndex !== null && (
        <div
          onClick={() => setSelectedAssetIndex(null)}
          style={{
            position: 'fixed',
            inset: 0,
            backgroundColor: 'rgba(0, 0, 0, 0.88)',
            backdropFilter: 'blur(10px)',
            zIndex: 1000,
            display: 'flex',
            alignItems: 'center',
            justifyContent: 'center',
            padding: '20px',
          }}
        >
          <div
            onClick={(e) => e.stopPropagation()}
            className="modal-responsive"
            style={{
              backgroundColor: 'var(--bg-surface)',
              borderRadius: '24px',
              maxWidth: '720px',
              width: '100%',
              overflow: 'hidden',
              boxShadow: '0 25px 60px rgba(0,0,0,0.6)',
              border: '1px solid var(--border-color)',
              position: 'relative',
              maxHeight: '92vh',
              overflowY: 'auto',
            }}
          >
            {/* Image Header with Navigation Arrows */}
            <div style={{ height: 'clamp(220px, 48vw, 360px)', position: 'relative' }}>
              <img
                src={SACCO_ASSETS[selectedAssetIndex].image}
                alt={SACCO_ASSETS[selectedAssetIndex].title}
                style={{ width: '100%', height: '100%', objectFit: 'cover' }}
              />

              {/* Prev Button */}
              <button
                onClick={() => setSelectedAssetIndex((selectedAssetIndex - 1 + totalSlides) % totalSlides)}
                title="Previous Asset (Left Arrow)"
                style={{
                  position: 'absolute',
                  left: '16px',
                  top: '50%',
                  transform: 'translateY(-50%)',
                  width: '40px',
                  height: '40px',
                  borderRadius: '50%',
                  backgroundColor: 'rgba(0,0,0,0.65)',
                  color: '#FFFFFF',
                  border: 'none',
                  cursor: 'pointer',
                  display: 'flex',
                  alignItems: 'center',
                  justifyContent: 'center',
                  transition: 'all 0.2s',
                }}
              >
                <ChevronLeft size={22} />
              </button>

              {/* Next Button */}
              <button
                onClick={() => setSelectedAssetIndex((selectedAssetIndex + 1) % totalSlides)}
                title="Next Asset (Right Arrow)"
                style={{
                  position: 'absolute',
                  right: '16px',
                  top: '50%',
                  transform: 'translateY(-50%)',
                  width: '40px',
                  height: '40px',
                  borderRadius: '50%',
                  backgroundColor: 'rgba(0,0,0,0.65)',
                  color: '#FFFFFF',
                  border: 'none',
                  cursor: 'pointer',
                  display: 'flex',
                  alignItems: 'center',
                  justifyContent: 'center',
                  transition: 'all 0.2s',
                }}
              >
                <ChevronRight size={22} />
              </button>

              {/* Close Button */}
              <button
                onClick={() => setSelectedAssetIndex(null)}
                style={{
                  position: 'absolute',
                  top: '16px',
                  right: '16px',
                  width: '36px',
                  height: '36px',
                  borderRadius: '50%',
                  backgroundColor: 'rgba(0,0,0,0.65)',
                  color: '#FFFFFF',
                  border: 'none',
                  cursor: 'pointer',
                  display: 'flex',
                  alignItems: 'center',
                  justifyContent: 'center',
                }}
              >
                <X size={18} />
              </button>

              <div style={{ position: 'absolute', bottom: '16px', left: '16px', display: 'flex', gap: '8px' }}>
                <span className="badge badge-lime">{SACCO_ASSETS[selectedAssetIndex].categoryLabel}</span>
                <span
                  style={{
                    backgroundColor: 'rgba(0,0,0,0.7)',
                    color: '#FFFFFF',
                    padding: '4px 10px',
                    borderRadius: '50px',
                    fontSize: '0.72rem',
                    fontWeight: 700,
                  }}
                >
                  Asset {selectedAssetIndex + 1} of {totalSlides}
                </span>
              </div>
            </div>

            <div style={{ padding: '28px' }}>
              <h3 style={{ fontSize: '1.35rem', fontWeight: 800, color: 'var(--text-main)', marginBottom: '10px' }}>
                {SACCO_ASSETS[selectedAssetIndex].title}
              </h3>
              <p style={{ color: 'var(--text-muted)', fontSize: '0.92rem', lineHeight: 1.6, marginBottom: '20px' }}>
                {SACCO_ASSETS[selectedAssetIndex].description}
              </p>

              <div
                style={{
                  display: 'grid',
                  gridTemplateColumns: 'repeat(3, 1fr)',
                  gap: '14px',
                  backgroundColor: 'var(--surface-2)',
                  borderRadius: '16px',
                  padding: '16px',
                  marginBottom: '24px',
                }}
              >
                <div>
                  <div style={{ fontSize: '0.72rem', color: 'var(--text-muted)', textTransform: 'uppercase' }}>Location</div>
                  <div style={{ fontWeight: 700, fontSize: '0.88rem', marginTop: '2px', color: 'var(--text-main)' }}>
                    {SACCO_ASSETS[selectedAssetIndex].location}
                  </div>
                </div>
                <div>
                  <div style={{ fontSize: '0.72rem', color: 'var(--text-muted)', textTransform: 'uppercase' }}>Annual ROI Yield</div>
                  <div style={{ fontWeight: 800, fontSize: '0.88rem', marginTop: '2px', color: 'var(--brand-forest)' }}>
                    {SACCO_ASSETS[selectedAssetIndex].annualRoi}
                  </div>
                </div>
                <div>
                  <div style={{ fontSize: '0.72rem', color: 'var(--text-muted)', textTransform: 'uppercase' }}>Asset Valuation</div>
                  <div style={{ fontWeight: 800, fontSize: '0.88rem', marginTop: '2px', color: 'var(--brand-forest)' }}>
                    {SACCO_ASSETS[selectedAssetIndex].valuation}
                  </div>
                </div>
              </div>

              <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                <span style={{ fontSize: '0.78rem', color: 'var(--text-muted)' }}>Use arrow keys ◀ ▶ to navigate</span>
                <div style={{ display: 'flex', gap: '10px' }}>
                  <button
                    type="button"
                    onClick={() => setSelectedAssetIndex(null)}
                    className="btn btn-ghost"
                  >
                    Close
                  </button>
                  <Link href="/register" className="btn btn-lime">
                    Become a Co-Owner <ArrowRight size={16} />
                  </Link>
                </div>
              </div>
            </div>
          </div>
        </div>
      )}

      {/* ═════════════════════════════════════════════════════════════════════
          PRODUCT DETAIL MODAL
      ═════════════════════════════════════════════ */}
      {selectedProduct && (
        <div
          onClick={() => setSelectedProduct(null)}
          style={{
            position: 'fixed',
            inset: 0,
            backgroundColor: 'rgba(0, 0, 0, 0.8)',
            backdropFilter: 'blur(8px)',
            zIndex: 1000,
            display: 'flex',
            alignItems: 'center',
            justifyContent: 'center',
            padding: '16px',
          }}
        >
          <div
            onClick={(e) => e.stopPropagation()}
            className="modal-responsive"
            style={{
              backgroundColor: 'var(--bg-surface)',
              borderRadius: '24px',
              maxWidth: '620px',
              width: '100%',
              overflow: 'hidden',
              boxShadow: '0 25px 60px rgba(0,0,0,0.5)',
              border: '1px solid var(--border-color)',
              padding: '30px 24px',
              maxHeight: '92vh',
              overflowY: 'auto',
            }}
          >
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', marginBottom: '16px' }}>
              <div>
                <span className="badge badge-lime" style={{ marginBottom: '8px' }}>{selectedProduct.badge}</span>
                <h3 style={{ fontSize: '1.4rem', fontWeight: 800, color: 'var(--text-main)' }}>{selectedProduct.name}</h3>
              </div>
              <button
                onClick={() => setSelectedProduct(null)}
                style={{
                  background: 'none',
                  border: 'none',
                  cursor: 'pointer',
                  color: 'var(--text-muted)',
                }}
              >
                <X size={22} />
              </button>
            </div>

            <p style={{ color: 'var(--text-muted)', fontSize: '0.94rem', lineHeight: 1.6, marginBottom: '20px' }}>
              {selectedProduct.description}
            </p>

            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '14px', backgroundColor: 'var(--surface-2)', padding: '16px', borderRadius: '16px', marginBottom: '24px' }}>
              <div>
                <span style={{ fontSize: '0.74rem', color: 'var(--text-muted)', textTransform: 'uppercase' }}>Interest / Yield</span>
                <div style={{ fontWeight: 800, color: 'var(--brand-forest)', fontSize: '1.05rem', marginTop: '2px' }}>{selectedProduct.interestRate}</div>
              </div>
              <div>
                <span style={{ fontSize: '0.74rem', color: 'var(--text-muted)', textTransform: 'uppercase' }}>Maximum Limit</span>
                <div style={{ fontWeight: 800, color: 'var(--brand-forest)', fontSize: '1.05rem', marginTop: '2px' }}>{selectedProduct.maxAmount}</div>
              </div>
            </div>

            <h4 style={{ fontSize: '0.92rem', fontWeight: 800, color: 'var(--text-main)', marginBottom: '10px' }}>Key Product Features</h4>
            <ul style={{ listStyle: 'none', display: 'flex', flexDirection: 'column', gap: '8px', marginBottom: '20px' }}>
              {selectedProduct.features.map((f, i) => (
                <li key={i} style={{ fontSize: '0.88rem', color: 'var(--text-main)', display: 'flex', alignItems: 'center', gap: '8px' }}>
                  <CheckCircle2 size={15} color="#16a34a" /> {f}
                </li>
              ))}
            </ul>

            <h4 style={{ fontSize: '0.92rem', fontWeight: 800, color: 'var(--text-main)', marginBottom: '10px' }}>Eligibility Requirements</h4>
            <ul style={{ listStyle: 'none', display: 'flex', flexDirection: 'column', gap: '8px', marginBottom: '28px' }}>
              {selectedProduct.requirements.map((r, i) => (
                <li key={i} style={{ fontSize: '0.88rem', color: 'var(--text-muted)', display: 'flex', alignItems: 'center', gap: '8px' }}>
                  <span style={{ width: '6px', height: '6px', borderRadius: '50%', backgroundColor: 'var(--brand-forest)' }} /> {r}
                </li>
              ))}
            </ul>

            <div style={{ display: 'flex', justifyContent: 'flex-end', gap: '12px' }}>
              <button
                type="button"
                onClick={() => setSelectedProduct(null)}
                className="btn btn-ghost"
              >
                Close
              </button>
              <Link href={`/register?product=${selectedProduct.id}`} className="btn btn-lime">
                Apply for this Product <ArrowRight size={16} />
              </Link>
            </div>
          </div>
        </div>
      )}

      {/* ═════════════════════════════════════════════════════════════════════
          FLOATING QUICK LOAN ESTIMATOR BUTTON & DRAWER
      ═════════════════════════════════════════════ */}
      <button
        onClick={() => setFloatingDrawerOpen(true)}
        className="floating-assistant-btn"
        title="Quick Loan Calculator"
      >
        <Zap size={16} /> Quick Loan Calculator
      </button>

      {/* Quick Calculator Slide-Out Drawer */}
      {floatingDrawerOpen && (
        <div
          onClick={() => setFloatingDrawerOpen(false)}
          style={{
            position: 'fixed',
            inset: 0,
            backgroundColor: 'rgba(0, 0, 0, 0.6)',
            backdropFilter: 'blur(4px)',
            zIndex: 1050,
            display: 'flex',
            justifyContent: 'flex-end',
          }}
        >
          <div
            onClick={(e) => e.stopPropagation()}
            style={{
              width: '100%',
              maxWidth: 'min(420px, 100vw)',
              height: '100%',
              backgroundColor: 'var(--bg-surface)',
              boxShadow: '-10px 0 30px rgba(0,0,0,0.3)',
              padding: '24px 20px',
              display: 'flex',
              flexDirection: 'column',
              justifyContent: 'space-between',
              overflowY: 'auto',
            }}
          >
            <div>
              <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '20px' }}>
                <div style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
                  <Calculator size={20} color="var(--brand-forest)" />
                  <h3 style={{ fontSize: '1.2rem', fontWeight: 800, color: 'var(--text-main)' }}>Instant Loan Estimate</h3>
                </div>
                <button
                  onClick={() => setFloatingDrawerOpen(false)}
                  style={{ background: 'none', border: 'none', cursor: 'pointer', color: 'var(--text-muted)' }}
                >
                  <X size={20} />
                </button>
              </div>

              <div style={{ marginBottom: '20px' }}>
                <label className="input-label">Loan Type</label>
                <select
                  value={loanType}
                  onChange={(e) => setLoanType(e.target.value as any)}
                  className="form-select"
                >
                  <option value="express">15-Min Mobile Express (8% p.a.)</option>
                  <option value="dev">Super Development Loan (10% p.a.)</option>
                  <option value="asset">Vehicle & Matatu Financing (12% p.a.)</option>
                  <option value="emergency">Emergency Relief (6% p.a.)</option>
                </select>
              </div>

              <div style={{ marginBottom: '20px' }}>
                <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: '6px' }}>
                  <label className="input-label" style={{ margin: 0 }}>Amount</label>
                  <span style={{ fontWeight: 800, color: 'var(--brand-forest)' }}>{formatKES(loanAmount)}</span>
                </div>
                <input
                  type="range"
                  min={currentRateObj.minAmt}
                  max={currentRateObj.maxAmt}
                  step={5000}
                  value={loanAmount}
                  onChange={(e) => setLoanAmount(Number(e.target.value))}
                  className="landing-slider"
                />
              </div>

              <div style={{ marginBottom: '24px' }}>
                <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: '6px' }}>
                  <label className="input-label" style={{ margin: 0 }}>Duration</label>
                  <span style={{ fontWeight: 800, color: 'var(--brand-forest)' }}>{loanMonths} Months</span>
                </div>
                <input
                  type="range"
                  min={currentRateObj.minMos}
                  max={currentRateObj.maxMos}
                  step={1}
                  value={loanMonths}
                  onChange={(e) => setLoanMonths(Number(e.target.value))}
                  className="landing-slider"
                />
              </div>

              {/* Quick Results Box */}
              <div
                style={{
                  backgroundColor: 'var(--brand-forest)',
                  color: '#FFFFFF',
                  padding: '20px',
                  borderRadius: '16px',
                  marginBottom: '20px',
                }}
              >
                <div style={{ fontSize: '0.75rem', color: 'rgba(255,255,255,0.7)' }}>Estimated Monthly Payment</div>
                <div style={{ fontSize: '1.8rem', fontWeight: 800, color: 'var(--brand-lime)', margin: '4px 0 12px' }}>
                  {formatKES(monthlyInstallment)}
                </div>
                <div style={{ display: 'flex', justifyContent: 'space-between', fontSize: '0.8rem', borderTop: '1px solid rgba(255,255,255,0.15)', paddingTop: '10px' }}>
                  <span>Total Repayable:</span>
                  <b>{formatKES(totalRepayable)}</b>
                </div>
              </div>
            </div>

            <div>
              <Link
                href={`/register?type=loan&amount=${loanAmount}&months=${loanMonths}&product=${loanType}`}
                onClick={() => setFloatingDrawerOpen(false)}
                className="btn btn-lime"
                style={{ width: '100%', justifyContent: 'center', padding: '12px' }}
              >
                Proceed to Apply <ArrowRight size={16} />
              </Link>
            </div>
          </div>
        </div>
      )}

      {/* ═════════════════════════════════════════════════════════════════════
          FLOATING SCROLL-TO-TOP BUTTON
      ═════════════════════════════════════════════ */}
      {showScrollTop && (
        <button
          onClick={() => window.scrollTo({ top: 0, behavior: 'smooth' })}
          className="floating-scroll-top"
          title="Scroll to top"
        >
          <ArrowUp size={18} />
        </button>
      )}

      {/* ═════════════════════════════════════════════════════════════════════
          LIVE SOCIAL PROOF TICKER TOAST
      ═════════════════════════════════════════════ */}
      {socialToast && (
        <div className="social-proof-toast">
          <div
            style={{
              width: '36px',
              height: '36px',
              borderRadius: '50%',
              backgroundColor: 'var(--brand-forest)',
              color: 'var(--brand-lime)',
              display: 'flex',
              alignItems: 'center',
              justifyContent: 'center',
              fontWeight: 800,
              fontSize: '0.85rem',
              flexShrink: 0,
            }}
          >
            ⚡
          </div>
          <div style={{ flex: 1 }}>
            <div style={{ fontWeight: 700, fontSize: '0.82rem', color: 'var(--text-main)' }}>
              {socialToast.name}
            </div>
            <div style={{ fontSize: '0.75rem', color: 'var(--text-muted)' }}>
              {socialToast.action} • <span style={{ color: 'var(--brand-forest)', fontWeight: 600 }}>{socialToast.time}</span>
            </div>
          </div>
          <button
            onClick={() => setSocialToast(null)}
            style={{ background: 'none', border: 'none', cursor: 'pointer', color: 'var(--text-muted)', padding: '2px' }}
          >
            <X size={14} />
          </button>
        </div>
      )}

      <Footer />
    </div>
  );
}
