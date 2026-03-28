import React from 'react';

export default function ReadinessCheck({ steps, activeStep, onStepClick }) {
  const completed = steps.filter(s => s.ok).length;
  const allOk     = completed === steps.length;
  const nextIdx   = steps.findIndex(s => !s.ok);

  return (
    <div className="card shadow-sm mb-4">
      <div className="card-header bg-dark text-white py-3">
        <div className="fw-bold">Setup Progress</div>
        <div className="text-white-50 small mt-1">
          {allOk
            ? 'All steps complete — ready to launch'
            : `${steps.length - completed} step${steps.length - completed !== 1 ? 's' : ''} remaining`}
        </div>
      </div>

      <div className="card-body py-3 px-2">
        <div className="d-flex align-items-start">
          {steps.map((step, i) => {
            const done     = step.ok;
            const isActive = i === activeStep;
            const isNext   = i === nextIdx;
            const isLast   = i === steps.length - 1;
            const canClick = done || i === 0 || steps[i - 1]?.ok;

            const nodeClass = done     ? 'bg-success border-success text-white'
                            : isActive ? 'bg-body border-warning text-warning'
                            : isNext   ? 'bg-body border-primary text-primary'
                            :            'bg-body border-secondary text-secondary';

            const labelClass = isActive ? 'text-warning fw-bold'
                             : done     ? 'text-success fw-bold'
                             : isNext   ? 'text-primary fw-semibold'
                             :            'text-muted';

            const lineClass = done ? 'border-success' : 'border-secondary';

            return (
              <React.Fragment key={i}>
                <div
                  className={`d-flex flex-column align-items-center flex-shrink-0 ${!canClick ? 'opacity-50' : ''}`}
                  style={{ cursor: canClick ? 'pointer' : 'not-allowed' }}
                  onClick={() => { if (canClick) onStepClick(i); }}
                  title={`${step.label}: ${step.sub}`}
                >
                  <div
                    className={`rounded-circle border border-2 d-flex align-items-center justify-content-center fw-bold ${nodeClass}`}
                    style={{ width: '32px', height: '32px', fontSize: done ? '14px' : '11px', transition: 'all 0.25s' }}
                  >
                    {done ? '✓' : i + 1}
                  </div>
                  <div className="text-center mt-1" style={{ width: '52px' }}>
                    <div className={labelClass} style={{ fontSize: '10px', whiteSpace: 'nowrap', overflow: 'hidden', textOverflow: 'ellipsis' }}>
                      {step.label}
                    </div>
                    <div className="text-muted" style={{ fontSize: '9px', whiteSpace: 'nowrap' }}>
                      {step.sub}
                    </div>
                  </div>
                </div>

                {!isLast && (
                  <div
                    className={`flex-fill border-top border-2 ${lineClass}`}
                    style={{ marginTop: '15px', minWidth: '4px', transition: 'border-color 0.4s' }}
                  />
                )}
              </React.Fragment>
            );
          })}
        </div>
      </div>
    </div>
  );
}
