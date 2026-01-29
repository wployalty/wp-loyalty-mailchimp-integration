const Image = ({
                   image,
                   height = "h-6",
                   width = "w-6",
                   objectFit = "object-contain",
                   alt = "image"
               }) => {
    return <img className={`${height} ${width} ${objectFit} rounded-md`} alt={alt}
                src={image}
    />
}
export default Image;