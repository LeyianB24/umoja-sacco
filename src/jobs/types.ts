export interface JobArgs {
  dryRun?: boolean;
  fix?: boolean;
  batchSize?: number;
  period?: number;
  [key: string]: any;
}

export interface JobResult {
  jobName: string;
  success: boolean;
  recordsProcessed: number;
  message: string;
  durationMs: number;
  details?: any;
}

export interface JobDefinition {
  name: string;
  description: string;
  schedule: string;
  run: (args: JobArgs) => Promise<JobResult>;
}
