import React from 'react'

const ShimmerLoading = ({height, width = "w-full", wrapperWidth = "w-full"}) => {
    return (
        <div className={`shimmer ${wrapperWidth} h-auto flex items-center justify-start`}>
            <div className={` ${width} ${height} bg-shimmer shadow-md rounded-md`}/>
        </div>
    )
}

export default ShimmerLoading
