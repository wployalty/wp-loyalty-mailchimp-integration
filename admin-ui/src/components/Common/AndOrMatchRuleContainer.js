import React from 'react';

const AndOrMatchRuleContainer = ({Icon, click, label, textColor}) => {
    return <div
        onClick={click}
        className={`flex items-center gap-x-1`}
    >
        <span>{Icon}</span>
        <span className={` ${textColor} text-xs 2xl:text-sm font-semibold `}>{label}</span>
    </div>
};

export default AndOrMatchRuleContainer;