// src/content/site-content.ts
// Structured Content Repository for Umoja Sacco Society Ltd
// Adheres to Project Operating Rules: Content Separation from JSX

export interface SaccoAsset {
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

export interface SaccoProduct {
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

export interface LoanRateConfig {
  rate: number;
  label: string;
  minAmt: number;
  maxAmt: number;
  minMos: number;
  maxMos: number;
}

export const SACCO_ASSETS: SaccoAsset[] = [
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

export const SACCO_PRODUCTS: SaccoProduct[] = [
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

export const LOAN_RATES: Record<string, LoanRateConfig> = {
  express: { rate: 0.08, label: 'Express Mobile (8% p.a.)', minAmt: 5000, maxAmt: 100000, minMos: 1, maxMos: 6 },
  dev: { rate: 0.10, label: 'Development Loan (10% p.a.)', minAmt: 20000, maxAmt: 2000000, minMos: 3, maxMos: 36 },
  asset: { rate: 0.12, label: 'Asset & Vehicle Financing (12% p.a.)', minAmt: 100000, maxAmt: 4000000, minMos: 6, maxMos: 48 },
  emergency: { rate: 0.06, label: 'Emergency / School Fees (6% p.a.)', minAmt: 5000, maxAmt: 80000, minMos: 1, maxMos: 4 },
};
