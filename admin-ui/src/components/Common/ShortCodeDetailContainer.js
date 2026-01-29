import React from 'react';

const ShortCodeDetailContainer = ({label, value}) => {
    return <div className={`w-full flex flex-col  p-1.5  gap-y-1`}>
        <p className={`  text-dark   2xl:text-md text-sm font-medium`}>{value}</p>
        <p className={` text-light   text-xs font-normal`}>{label}</p>
    </div>
};
export default ShortCodeDetailContainer;