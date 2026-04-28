import React from 'react'

const ShimmerLoading = ({height, width = "wlmi-w-full", wrapperWidth = "wlmi-w-full"}) => {
    return (
        <div className={`shimmer ${wrapperWidth} wlmi-h-auto wlmi-flex wlmi-items-center wlmi-justify-start`}>
            <div className={` ${width} ${height} wlmi-bg-shimmer wlmi-shadow-md wlmi-rounded-md`}/>
        </div>
    )
}

export default ShimmerLoading
