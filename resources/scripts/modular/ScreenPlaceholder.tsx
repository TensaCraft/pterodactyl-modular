import React from 'react';

export const ScreenPlaceholder = () => (
    <div className={'flex min-h-[40vh] items-center justify-center px-6 py-10'}>
        <div className={'max-w-xl rounded-lg bg-neutral-100 px-6 py-10 text-center shadow-sm'}>
            <h2 className={'text-2xl font-semibold text-neutral-900'}>Screen unavailable</h2>
            <p className={'mt-2 text-sm text-neutral-600'}>This route has no registered frontend component yet.</p>
        </div>
    </div>
);

export default ScreenPlaceholder;
